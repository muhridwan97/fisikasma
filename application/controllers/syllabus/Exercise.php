<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Exercise
 * @property CurriculumModel $curriculum
 * @property LessonModel $lesson
 * @property ExerciseModel $exercise
 * @property QuestionModel $question
 * @property AnswerChoicesModel $answerChoice
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Exercise extends App_Controller
{
	/**
	 * Exercise constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('QuestionModel', 'question');
		$this->load->model('AnswerChoicesModel', 'answerChoice');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');

		$this->setFilterMethods([
			'print_question' => 'GET',
			'print_answer' => 'GET',
		]);
	}

	/**
	 * Show exercise index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_VIEW);

		$filters = $_GET;

		if ($this->input->get('export')) {
			$exercises = $this->exercise->getAll($filters);
			$this->exporter->exportFromArray('Exercises', $exercises);
		} else {
			$exercises = $this->exercise->getAllWithPagination($filters);
			foreach ($exercises['data'] as &$exercise) {
				$this->morphToExercise($exercise);
			}
			$this->render('exercise/index', compact('exercises'));
		}
	}

	/**
	 * Show exercise data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_VIEW);

		$exercise = $this->exercise->getById($id);
		$this->morphToExercise($exercise);

		$questions = $this->getExerciseQuestion($id);

		$this->render('exercise/view', compact('exercise', 'questions'));
	}

	/**
	 * Print question list only.
	 *
	 * @param $id
	 */
	public function print_question($id)
	{
		AuthorizationModel::mustAuthorized([PERMISSION_EXERCISE_VIEW, PERMISSION_TRAINING_VIEW]);

		$exercise = $this->exercise->getById($id);
		$this->morphToExercise($exercise);
		$questions = $this->getExerciseQuestion($id);

		$html = $this->renderView('exercise/print_question', compact('exercise', 'questions'), true);

		$this->exporter->exportToPdf('Print Question', $html);
	}

	/**
	 * Print answer list only.
	 *
	 * @param $id
	 */
	public function print_answer($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_VIEW);

		$exercise = $this->exercise->getById($id);
		$this->morphToExercise($exercise);
		$questions = $this->getExerciseQuestion($id);

		$html = $this->renderView('exercise/print_answer', compact('exercise', 'questions'), true);

		$this->exporter->exportToPdf('Print Answer', $html);
	}

	/**
	 * Get morph-to data of exercise.
	 *
	 * @param $exercise
	 */
	private function morphToExercise(&$exercise)
	{
		switch ($exercise['type']) {
			case ExerciseModel::TYPE_LESSON_EXERCISE:
				$exercise['morph_to'] = $this->lesson->getById($exercise['id_reference']);
				$exercise['reference_title'] = $exercise['morph_to']['lesson_title'];
				break;
			case ExerciseModel::TYPE_CURRICULUM_EXAM:
				$exercise['morph_to'] = $this->curriculum->getById($exercise['id_reference']);
				$exercise['reference_title'] = $exercise['morph_to']['curriculum_title'];
				break;
			default:
				$exercise['morph_to'] = [];
				$exercise['reference_title'] = '';
				break;
		}
	}

	/**
	 * Get exercise question with answers.
	 *
	 * @param $exerciseId
	 * @return array|CI_DB_query_builder|mixed
	 */
	private function getExerciseQuestion($exerciseId)
	{
		$questions = $this->question->getAll([
			'exercise' => $exerciseId,
			'sort_by' => 'questions.question_order',
		]);
		foreach ($questions as &$question) {
			$question['answer_choices'] = $this->answerChoice->getAll([
				'question' => $question['id'],
				'sort_by' => 'answer_choices.id',
			]);
		}

		return $questions;
	}

	/**
	 * Show create exercise.
	 *
	 * @param null $type
	 * @param null $referenceId
	 */
	public function create($type = null, $referenceId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_CREATE);

		if (!in_array($type, [null, ExerciseModel::TYPE_LESSON_EXERCISE, ExerciseModel::TYPE_CURRICULUM_EXAM])) {
			flash('danger', 'Invalid exercise type', '_back');
		}

		$exercisable = null;
		switch ($type) {
			case ExerciseModel::TYPE_LESSON_EXERCISE:
				$exercisable = $this->lesson->getById($referenceId);
				break;
			case ExerciseModel::TYPE_CURRICULUM_EXAM:
				$exercisable = $this->curriculum->getById($referenceId);
				break;
		}

		$this->render('exercise/create', compact('type', 'referenceId', 'exercisable'));
	}

	/**
	 * Save new exercise data.
	 */
	public function save()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_CREATE);

		if ($this->validate()) {
			$type = $this->input->post('type');
			$referenceId = $this->input->post('reference_id');
			$exerciseTitle = $this->input->post('exercise_title');
			$category = $this->input->post('category');
			$questionSequence = $this->input->post('question_sequence');
			$hour = $this->input->post('duration_hour');
			$minute = $this->input->post('duration_minute');
			$second = $this->input->post('duration_second');
			$duration = if_empty($hour, '00') . ':' . if_empty($minute, '00') . ':' . if_empty($second, '00');
			$description = $this->input->post('description');
			$questions = if_empty($this->input->post('questions'), []);

			$this->db->trans_start();

			$this->exercise->create([
				'type' => $type,
				'id_reference' => if_empty($referenceId, null),
				'exercise_title' => $exerciseTitle,
				'category' => $category,
				'question_sequence' => $questionSequence,
				'duration' => $duration,
				'description' => $description,
			]);
			$exerciseId = $this->db->insert_id();

			foreach ($questions as $questionIndex => $question) {
				// find correct answer from choices
				$questionAnswer = '';
				if ($category == ExerciseModel::CATEGORY_CHOICES) {
					$answers = get_if_exist($question, 'answers', []);
					foreach ($answers as $answer) {
						if (!empty(get_if_exist($answer, 'is_correct_answer'))) {
							$questionAnswer = trim($answer['answer']);
							break;
						}
					}
				}

				// upload attachment
				$attachment = '';
				if (!empty($_FILES['questions']['name'][$questionIndex]['attachment'])) {
					$_FILES['question_temp']['name'] = $_FILES['questions']['name'][$questionIndex]['attachment'];
					$_FILES['question_temp']['type'] = $_FILES['questions']['type'][$questionIndex]['attachment'];
					$_FILES['question_temp']['tmp_name'] = $_FILES['questions']['tmp_name'][$questionIndex]['attachment'];
					$_FILES['question_temp']['error'] = $_FILES['questions']['error'][$questionIndex]['attachment'];
					$_FILES['question_temp']['size'] = $_FILES['questions']['size'][$questionIndex]['attachment'];
					$uploadAttachment = $this->uploader->setDriver('s3')->uploadTo('question_temp', [
						'destination' => 'questions/' . date('Y/m')
					]);
					if ($uploadAttachment) {
						$uploadedData = $this->uploader->getUploadedData();
						$attachment = $uploadedData['uploaded_path'];
					}
				}

				// insert question
				$this->question->create([
					'id_exercise' => $exerciseId,
					'question' => trim($question['question']),
					'hint' => trim($question['hint']),
					'answer' => get_if_exist($question, 'answer', $questionAnswer),
					'description' => $question['description'],
					'question_order' => $question['question_order'],
					'attachment' => $attachment,
				]);

				// add choices of the question
				if ($category == ExerciseModel::CATEGORY_CHOICES) {
					$questionId = $this->db->insert_id();
					foreach (get_if_exist($question, 'answers', []) as $answer) {
						$this->answerChoice->create([
							'id_question' => $questionId,
							'answer' => trim($answer['answer']),
							'is_correct_answer' => !empty(get_if_exist($answer, 'is_correct_answer')),
						]);
					}
				}
			}

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				flash('success', "Exercise {$exerciseTitle} successfully created", 'syllabus/exercise');
			} else {
				flash('danger', 'Create exercise failed, try again or contact administrator');
			}
		}
		$this->create();
	}

	/**
	 * Show edit exercise form.
	 *
	 * @param $id
	 */
	public function edit($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_EDIT);

		$exercise = $this->exercise->getById($id);
		$questions = $this->question->getAll([
			'exercise' => $id,
			'sort_by' => 'questions.question_order',
		]);
		foreach ($questions as &$question) {
			$question['answer_choices'] = $this->answerChoice->getAll([
				'question' => $question['id'],
				'sort_by' => 'answer_choices.id',
			]);
		}
		$exercisable = null;
		switch ($exercise['type']) {
			case ExerciseModel::TYPE_LESSON_EXERCISE:
				$exercisable = $this->lesson->getById($exercise['id_reference']);
				break;
			case ExerciseModel::TYPE_CURRICULUM_EXAM:
				$exercisable = $this->curriculum->getById($exercise['id_reference']);
				break;
		}

		$this->render('exercise/edit', compact('exercise', 'questions', 'exercisable'));
	}

	/**
	 * Update data exercise by id.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_EDIT);

		if ($this->validate()) {
			$exerciseTitle = $this->input->post('exercise_title');
			$questionSequence = $this->input->post('question_sequence');
			$hour = $this->input->post('duration_hour');
			$minute = $this->input->post('duration_minute');
			$second = $this->input->post('duration_second');
			$duration = if_empty($hour, '00') . ':' . if_empty($minute, '00') . ':' . if_empty($second, '00');
			$description = $this->input->post('description');
			$questions = if_empty($this->input->post('questions'), []);

			$exercise = $this->exercise->getById($id);
			$existingQuestions = $this->question->getAll(['exercise' => $id]);

			$this->db->trans_start();

			$this->exercise->update([
				'exercise_title' => $exerciseTitle,
				'question_sequence' => $questionSequence,
				'duration' => $duration,
				'description' => $description,
			], $id);

			// remove existing question
			$newQuestionIds = array_column($questions, 'id');
			foreach ($existingQuestions as $existingQuestion) {
				if (!in_array($existingQuestion['id'], $newQuestionIds)) {
					$this->question->delete($existingQuestion['id'], true);
				}
			}

			// create or update existing question data
			foreach ($questions as $questionIndex => $question) {
				// find correct answer from list
				$questionAnswer = '';
				if ($exercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
					$answers = get_if_exist($question, 'answers', []);
					foreach ($answers as $answer) {
						if (!empty(get_if_exist($answer, 'is_correct_answer'))) {
							$questionAnswer = trim($answer['answer']);
							break;
						}
					}
				}

				$attachment = '';
				if (!empty($_FILES['questions']['name'][$questionIndex]['attachment'])) {
					$_FILES['question_temp']['name'] = $_FILES['questions']['name'][$questionIndex]['attachment'];
					$_FILES['question_temp']['type'] = $_FILES['questions']['type'][$questionIndex]['attachment'];
					$_FILES['question_temp']['tmp_name'] = $_FILES['questions']['tmp_name'][$questionIndex]['attachment'];
					$_FILES['question_temp']['error'] = $_FILES['questions']['error'][$questionIndex]['attachment'];
					$_FILES['question_temp']['size'] = $_FILES['questions']['size'][$questionIndex]['attachment'];
					$uploadAttachment = $this->uploader->setDriver('s3')->uploadTo('question_temp', [
						'destination' => 'questions/' . date('Y/m')
					]);
					if ($uploadAttachment) {
						$uploadedData = $this->uploader->getUploadedData();
						$attachment = $uploadedData['uploaded_path'];
					}
				}

				$dataQuestion = [
					'id_exercise' => $id,
					'question' => trim($question['question']),
					'hint' => trim($question['hint']),
					'answer' => get_if_exist($question, 'answer', $questionAnswer),
					'description' => $question['description'],
					'question_order' => $question['question_order'],
					'attachment' => $attachment,
				];

				if (empty($question['id'])) {
					$this->question->create($dataQuestion);

					if ($exercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
						$questionId = $this->db->insert_id();
						foreach (get_if_exist($question, 'answers', []) as $answer) {
							$this->answerChoice->create([
								'id_question' => $questionId,
								'answer' => trim($answer['answer']),
								'is_correct_answer' => !empty(get_if_exist($answer, 'is_correct_answer')),
							]);
						}
					}
				} else {
					$currentQuestion = $this->question->getById($question['id']);
					if (empty($attachment) && !empty($currentQuestion['attachment'])) {
						$dataQuestion['attachment'] = $currentQuestion['attachment'];
					}
					$this->question->update($dataQuestion, $question['id']);

					if ($exercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
						$existingAnswers = $this->answerChoice->getAll(['question' => $question['id']]);

						// remove existing answer
						$newAnswerIds = array_column(get_if_exist($question, 'answers', []), 'id');
						foreach ($existingAnswers as $existingAnswer) {
							if (!in_array($existingAnswer['id'], $newAnswerIds)) {
								$this->answerChoice->delete($existingAnswer['id'], true);
							}
						}

						// create or update existing answer data
						foreach (get_if_exist($question, 'answers', []) as $answer) {
							$dataAnswer = [
								'id_question' => $question['id'],
								'answer' => trim($answer['answer']),
								'is_correct_answer' => !empty(get_if_exist($answer, 'is_correct_answer')),
							];
							if (empty($answer['id'])) {
								$this->answerChoice->create($dataAnswer);
							} else {
								$this->answerChoice->update($dataAnswer, $answer['id']);
							}
						}
					}
				}
			}

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				flash('success', "Exercise {$exerciseTitle} successfully updated", 'syllabus/exercise');
			} else {
				flash('danger', "Update exercise failed, try again or contact administrator");
			}
		}
		$this->edit($id);
	}

	/**
	 * Perform deleting exercise data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXERCISE_DELETE);

		$exercise = $this->exercise->getById($id);

		if ($this->exercise->delete($id, true)) {
			flash('warning', "Exercise {$exercise['exercise_title']} successfully deleted");
		} else {
			flash('danger', 'Delete exercise failed, try again or contact administrator');
		}
		redirect('syllabus/exercise');
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'exercise_title' => 'trim|required|max_length[100]',
			'category' => 'required|in_list[CHOICES,ESSAY,PRACTICE]',
			'question_sequence' => 'required|in_list[IN ORDER,RANDOM]',
			'duration_hour' => 'required',
			'duration_minute' => 'required',
			'duration_second' => 'required',
			'description' => 'max_length[500]',
			'questions[]' => 'required',
		];
	}
}
