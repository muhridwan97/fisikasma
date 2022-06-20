<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Classroom
 * @property TrainingModel $training
 * @property EmployeeModel $employee
 * @property CurriculumModel $curriculum
 * @property CourseModel $course
 * @property LessonModel $lesson
 * @property ExerciseModel $exercise
 * @property QuestionModel $question
 * @property AnswerChoicesModel $answerChoice
 * @property TrainingExerciseScoreModel $trainingExerciseScore
 * @property UserModel $user
 * @property StatusHistoryModel $statusHistory
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Classroom extends App_Controller
{
	protected $layout = 'layouts/classroom';

	/**
	 * Classroom constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('TrainingModel', 'training');
		$this->load->model('QuestionModel', 'question');
		$this->load->model('AnswerChoicesModel', 'answerChoice');
		$this->load->model('EmployeeModel', 'employee');
		$this->load->model('UserModel', 'user');
		$this->load->model('TrainingExerciseScoreModel', 'trainingExerciseScore');
		$this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');

		$this->setFilterMethods([
			'course' => 'GET',
			'exercise' => 'POST',
			'exercise_result' => 'GET',
		]);
	}

	/**
	 * Get sidebar training data.
	 *
	 * @param $training
	 * @return array
	 */
	private function getSidebarContent($training)
	{
		$courses = $this->course->getAll([
			'status' => CourseModel::STATUS_ACTIVE,
			'sort_by' => 'course_order',
			'curriculum' => $training['id_curriculum']
		]);
		foreach ($courses as &$course) {
			$course['lessons'] = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => $course['id']
			]);
			foreach ($course['lessons'] as &$lesson) {
				$lesson['exercises'] = $this->exercise->getByMorph(ExerciseModel::TYPE_LESSON_EXERCISE, $lesson['id']);
			}
		}

		return compact('training', 'courses');
	}

	/**
	 * Show training data.
	 *
	 * @param $trainingId
	 * @param $courseId
	 */
	public function course($trainingId, $courseId = null)
	{
		$training = $this->training->getById($trainingId);

		$this->authorize($training, PERMISSION_TRAINING_VIEW);

		$data = $this->getSidebarContent($training);

		// populate data for main view
		$lessonId = $this->input->get('lesson');
		$exerciseId = $this->input->get('exercise');

		if (!empty($lessonId) && empty($exerciseId)) {
			$data['lesson'] = $this->lesson->getById($lessonId);
			$data['lesson']['exercises'] = $this->exercise->getByMorph(ExerciseModel::TYPE_LESSON_EXERCISE, $lessonId);

			$nextLesson = null;
			$nextCourse = null;
			$lessons = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => $data['lesson']['id_course']
			]);
			foreach ($lessons as $index => $lessonItem) {
				if ($lessonItem['id'] == $lessonId && $index < count($lessons) - 1) {
					$nextLesson = $lessons[$index + 1];
					break;
				}
			}
			foreach ($data['courses'] as $index => $courseItem) {
				if ($courseItem['id'] == $courseId && $index < count($data['courses']) - 1) {
					$nextCourse = $data['courses'][$index + 1];
					break;
				}
			}
			$data['nextLesson'] = $nextLesson;
			$data['nextCourse'] = $nextCourse;
			$this->render('classroom/lesson', $data);
		} elseif (!empty($exerciseId)) {
			$data['exercise'] = $this->exercise->getById($exerciseId);
			$data['exercise']['questions'] = $this->question->getAll([
				'exercise' => $data['exercise']['id'],
				'sort_by' => $data['exercise']['question_sequence'] == ExerciseModel::QUESTION_SEQUENCE_RANDOM
					? 'RAND()'
					: 'questions.question_order',
			]);
			foreach ($data['exercise']['questions'] as &$question) {
				$question['answer_choices'] = $this->answerChoice->getAll([
					'question' => $question['id'],
					'sort_random' => true,
				]);
			}
			$this->render('classroom/exercise', $data);
		} elseif (!empty($courseId)) {
			$data['course'] = $this->course->getById($courseId);
			$data['lessons'] = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => if_empty($data['course']['id'], -1)
			]);
			$this->render('classroom/course', $data);
		} else {
			$this->render('classroom/index', $data);
		}
	}

	/**
	 * Submit exercise answer.
	 *
	 * @param $trainingId
	 * @param $exerciseId
	 */
	public function exercise($trainingId, $exerciseId)
	{
		$training = $this->training->getById($trainingId);

		$this->authorize($training, PERMISSION_TRAINING_VIEW);

		$exercise = $this->exercise->getById($exerciseId);
		$questions = $this->question->getAll(['exercise' => $exercise['id']]);

		$answers = if_empty($this->input->post('answers'), []);

		$correct = 0;
		$score = 0;
		if ($exercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
			foreach ($questions as &$question) {
				$answerId = get_if_exist($answers, $question['id']);
				if ($question['id_correct_answer_choice'] == $answerId) {
					$question['result'] = 100;
					$correct += 1;
					$score += 100;
				} else {
					$question['result'] = 0;
				}
			}
			$score = $score / if_empty(count($questions), 1);

			$this->trainingExerciseScore->create([
				'id_training' => $trainingId,
				'id_exercise' => $exerciseId,
				'score' => $score,
				'correct' => $correct,
				'total_question' => $exercise['total_questions'],
			]);
		}
		$result = [
			'correct' => $correct,
			'score' => $score,
		];

		redirect("training/classroom/{$trainingId}/exercise-result/{$exerciseId}?exercise={$exerciseId}&result=" . base64_encode(json_encode($result)));
	}

	/**
	 * Showing exercise score and result.
	 *
	 * @param $trainingId
	 * @param $exerciseId
	 */
	public function exercise_result($trainingId, $exerciseId)
	{
		$training = $this->training->getById($trainingId);

		$this->authorize($training, PERMISSION_TRAINING_VIEW);

		$data = $this->getSidebarContent($training);

		$exercise = $this->exercise->getById($exerciseId);
		$exercise['questions'] = $this->question->getAll(['exercise' => $exercise['id']]);

		$result = $this->input->get('result');
		$resultData = json_decode(base64_decode($result), true);

		$data['lesson'] = $this->lesson->getById($exercise['id_reference']);
		$data['exercise'] = $exercise;
		$data['correct'] = get_if_exist($resultData, 'correct', 0);
		$data['score'] = get_if_exist($resultData, 'score', 0);
		$data['trainingExerciseScores'] = $this->trainingExerciseScore->getBy([
			'id_training' => $trainingId,
			'id_exercise' => $exerciseId,
		]);

		// get next lesson or exercise
		$nextExercise = null;
		$nextLesson = null;
		$exercises = $this->exercise->getByMorph(ExerciseModel::TYPE_LESSON_EXERCISE, $data['lesson']['id']);
		foreach ($exercises as $index => $exerciseItem) {
			if ($exerciseItem['id'] == $exercise['id'] && $index < count($exercises) - 1) {
				$nextExercise = $exercises[$index + 1];
				break;
			}
		}
		if (empty($nextExercise)) {
			$lessons = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => $data['lesson']['id_course']
			]);
			foreach ($lessons as $index => $lessonItem) {
				if ($lessonItem['id'] == $data['lesson']['id'] && $index < count($lessons) - 1) {
					$nextLesson = $lessons[$index + 1];
					break;
				}
			}
		}
		$data['nextExercise'] = $nextExercise;
		$data['nextLesson'] = $nextLesson;

		$this->render('classroom/exercise_result', $data);
	}

	/**
	 * Check if entity is authorized.
	 *
	 * @param $entity
	 * @param null $permission
	 */
	public function authorize($entity, $permission = null)
	{
		parent::authorize($entity, $permission);

		if (!is_string($entity)) {
			if ($entity['status'] == TrainingModel::STATUS_INACTIVE) {
				$message = "Training {$entity['curriculum_title']} is deactivated, only active training allowed";
				AuthorizationModel::redirectUnauthorized($message);
			}
			if ($entity['id_employee'] != UserModel::loginData('id_employee') && !AuthorizationModel::hasPermission(PERMISSION_TRAINING_MANAGE)) {
				$message = "You are unauthorized to access this training";
				AuthorizationModel::redirectUnauthorized($message);
			}
		}

	}
}
