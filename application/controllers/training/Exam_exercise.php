<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Exam_exercise
 * @property ExamModel $exam
 * @property ExamExerciseModel $examExercise
 * @property ExamExerciseAnswerModel $examExerciseAnswer
 * @property EmployeeModel $employee
 * @property ExerciseModel $exercise
 * @property QuestionModel $question
 * @property AnswerChoicesModel $answerChoice
 * @property UserModel $user
 * @property StatusHistoryModel $statusHistory
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Exam_exercise extends App_Controller
{

	/**
	 * Exam_exercise constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('ExamModel', 'exam');
		$this->load->model('ExamExerciseModel', 'examExercise');
		$this->load->model('ExamExerciseAnswerModel', 'examExerciseAnswer');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('QuestionModel', 'question');
		$this->load->model('AnswerChoicesModel', 'answerChoice');
		$this->load->model('EmployeeModel', 'employee');
		$this->load->model('UserModel', 'user');
		$this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('notifications/FinishExamNotification');

		$this->setFilterMethods([
			'start' => 'POST',
			'quiz' => 'GET',
			'submit_answer' => 'POST|PUT',
			'save_current_answer' => 'POST|PUT',
			'assessment' => 'GET',
			'submit_assessment' => 'PUT',
		]);
	}

	/**
	 * Start exam exercise.
	 *
	 * @param $id
	 */
	public function start($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_VIEW);

		$examExercise = $this->examExercise->getById($id);

		if ($examExercise['status'] != ExamExerciseModel::STATUS_PENDING) {
			flash('danger', "Exam {$examExercise['title']} is finished or already assessed", '_back');
		}

		if ($examExercise['id_employee'] != UserModel::loginData('id_employee') && $examExercise['id_evaluator'] != UserModel::loginData('id_employee')) {
			flash('danger', "You are is not the employee that assigned to do this exam", '_back');
		}

		$this->db->trans_start();

		$this->examExercise->update([
			'started_at' => date('Y-m-d H:i:s'),
			'status' => ExamExerciseModel::STATUS_STARTED
		], $id);

		$this->statusHistory->create([
			'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
			'id_reference' => $id,
			'status' => ExamExerciseModel::STATUS_STARTED,
			'description' => "Exam is started",
		]);

		$this->db->trans_complete();

		if ($this->db->trans_status()) {
			flash('success', "Exam exercise {$examExercise['title']} is started from now!");
			redirect('training/exam-exercise/quiz/' . $id);
		} else {
			flash('danger', 'Start exam failed, try again or contact your trainer', '_back');
		}

	}

	/**
	 * Show exam exercise.
	 *
	 * @param $id
	 */
	public function quiz($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_VIEW);

		$examExercise = $this->examExercise->getById($id);

		if ($examExercise['status'] != ExamExerciseModel::STATUS_STARTED) {
			flash('danger', "Exam {$examExercise['title']} is finished or already assessed", '_back');
		}

		if ($examExercise['id_employee'] != UserModel::loginData('id_employee') && $examExercise['id_evaluator'] != UserModel::loginData('id_employee')) {
			flash('danger', "You are is not the employee that assigned to do this exam", '_back');
		}

		$exam = $this->exam->getById($examExercise['id_exam']);

		$exercise = $this->exercise->getById($examExercise['id_exercise']);

		$timeLeft = $this->examExercise->getQuizTimeLeft($examExercise);
		if ($timeLeft < '00:00:00') {
			$timeLimit = $this->examExercise->getQuizTimeLimit($examExercise);
			$examExerciseStatus = $examExercise['category'] == ExerciseModel::CATEGORY_CHOICES
				? ExamExerciseModel::STATUS_ASSESSED
				: ExamExerciseModel::STATUS_FINISHED;
			$this->examExercise->update([
				'finished_at' => $timeLimit,
				'status' => $examExerciseStatus
			], $id);
			$this->statusHistory->create([
				'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
				'id_reference' => $id,
				'status' => $examExerciseStatus,
				'description' => "Exam is timeout, forced to be finished",
			]);
			flash('warning', 'Exam is over, outstanding exercise forced to be finished');
		} else {
			$existingAnswers = $this->examExerciseAnswer->getBy(['id_exam_exercise' => $id]);
			$questions = $this->question->getAll([
				'exercise' => $exercise['id'],
				'sort_by' => $exercise['question_sequence'] == ExerciseModel::QUESTION_SEQUENCE_RANDOM
					? 'RAND()'
					: 'questions.question_order',
			]);
			foreach ($questions as &$question) {
				foreach ($existingAnswers as $existingAnswer) {
					if ($question['id'] == $existingAnswer['id_question']) {
						$question['current_answer'] = $existingAnswer['answer'];
						break;
					}
				}
				$question['answer_choices'] = $this->answerChoice->getAll([
					'question' => $question['id'],
					'sort_random' => true,
				]);
			}

			$this->layout = 'layouts/quiz';

			$this->render('exam_exercise/quiz', compact('exam', 'examExercise', 'exercise', 'questions', 'timeLeft'));
		}
	}

	/**
	 * Submit question answer.
	 *
	 * @param $id
	 */
	public function submit_answer($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_VIEW);

		$examExercise = $this->examExercise->getById($id);

		if ($examExercise['status'] != ExamExerciseModel::STATUS_STARTED) {
			flash('danger', "Exam {$examExercise['title']} is finished or already assessed", '_back');
		}

		if ($examExercise['id_employee'] != UserModel::loginData('id_employee') && $examExercise['id_evaluator'] != UserModel::loginData('id_employee')) {
			flash('danger', "You are is not the employee that assigned to do this exam", '_back');
		}

		$exam = $this->exam->getById($examExercise['id_exam']);

		$questions = $this->question->getAll(['exercise' => $examExercise['id_exercise']]);

		$answers = if_empty($this->input->post('answers'), []);

		$this->db->trans_start();

		$this->examExerciseAnswer->delete(['id_exam_exercise' => $id]);

		foreach ($questions as $question) {
			if ($examExercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
				$answerId = get_if_exist($answers, $question['id']);
				$answerChoice = $this->answerChoice->getById($answerId);
				$this->examExerciseAnswer->create([
					'id_exam_exercise' => $id,
					'id_question' => $question['id'],
					'question' => $question['question'],
					'answer' => get_if_exist($answerChoice, 'answer', null),
					'score' => $question['id_correct_answer_choice'] == $answerId ? 100 : 0,
				]);
			} else {
				$answer = get_if_exist($answers, $question['id']);

				$attachment = '';
				if (!empty($_FILES['answers']['name'][$question['id']]['attachment'])) {
					$_FILES['answer_temp']['name'] = $_FILES['answers']['name'][$question['id']]['attachment'];
					$_FILES['answer_temp']['type'] = $_FILES['answers']['type'][$question['id']]['attachment'];
					$_FILES['answer_temp']['tmp_name'] = $_FILES['answers']['tmp_name'][$question['id']]['attachment'];
					$_FILES['answer_temp']['error'] = $_FILES['answers']['error'][$question['id']]['attachment'];
					$_FILES['answer_temp']['size'] = $_FILES['answers']['size'][$question['id']]['attachment'];
					$uploadAttachment = $this->uploader->setDriver('s3')->uploadTo('answer_temp', [
						'destination' => 'questions/' . date('Y/m')
					]);
					if ($uploadAttachment) {
						$uploadedData = $this->uploader->getUploadedData();
						$attachment = $uploadedData['uploaded_path'];
					}
				}

				$this->examExerciseAnswer->create([
					'id_exam_exercise' => $id,
					'id_question' => $question['id'],
					'question' => $question['question'],
					'answer' => if_empty(trim($answer['answer']), null),
					'attachment' => if_empty($attachment, null)
				]);
			}
		}

		$examExerciseStatus = $examExercise['category'] == ExerciseModel::CATEGORY_CHOICES
			? ExamExerciseModel::STATUS_ASSESSED
			: ExamExerciseModel::STATUS_FINISHED;
		$this->examExercise->update([
			'finished_at' => date('Y-m-d H:i:s'),
			'status' => $examExerciseStatus
		], $id);

		$this->statusHistory->create([
			'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
			'id_reference' => $id,
			'status' => $examExerciseStatus,
			'description' => "Exam answers is submitted",
		]);

		$this->db->trans_complete();

		if ($this->db->trans_status()) {
			if ($examExercise['category'] != ExerciseModel::CATEGORY_CHOICES) {
				$userEvaluator = $this->employee->getById($exam['id_evaluator']);
				$this->notification
					->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
					->to($this->user->getById($userEvaluator['id_user']))
					->send(new FinishExamNotification($examExercise));
			}

			flash('success', 'Your exam successfully saved review your score or waiting evaluator assessment');
			redirect('training/exam/view/' . $examExercise['id_exam']);
		} else {
			flash('danger', 'Save exam failed, try again or contact your trainer');
		}
		$this->quiz($id);
	}

	/**
	 * Submit current answer.
	 *
	 * @param $id
	 */
	public function save_current_answer($id)
	{
		$examExercise = $this->examExercise->getById($id);
		$answers = if_empty($this->input->post('answers'), []);

		$this->db->trans_start();

		$existingAnswers = $this->examExerciseAnswer->getBy(['id_exam_exercise' => $id]);

		// remove existing answer
		$newQuestionIds = array_keys($answers);
		$existingQuestionIds = array_column($existingAnswers, 'id_question');
		foreach ($existingQuestionIds as $existingQuestionId) {
			if (!in_array($existingQuestionId, $newQuestionIds)) {
				$this->examExerciseAnswer->delete([
					'id_exam_exercise' => $id,
					'id_question' => $existingQuestionId
				]);
			}
		}

		// create or update existing data
		foreach ($answers as $questionId => $answer) {
			// update
			if (in_array($questionId, $existingQuestionIds)) {
				if ($examExercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
					$answerChoice = $this->answerChoice->getById($answer);
					$this->examExerciseAnswer->update([
						'answer' => $answerChoice['answer'],
					], ['id_exam_exercise' => $id, 'id_question' => $questionId]);
				} else {
					$this->examExerciseAnswer->update([
						'answer' => $answer['answer'],
					], ['id_exam_exercise' => $id, 'id_question' => $questionId]);
				}
			} else {
				if ($examExercise['category'] == ExerciseModel::CATEGORY_CHOICES) {
					$answerChoice = $this->answerChoice->getById($answer);
					$this->examExerciseAnswer->create([
						'id_exam_exercise' => $id,
						'id_question' => $questionId,
						'answer' => $answerChoice['answer'],
					]);
				} else {
					$this->examExerciseAnswer->create([
						'id_exam_exercise' => $id,
						'id_question' => $questionId,
						'answer' => if_empty(trim($answer['answer']), null),
					]);
				}
			}
		}
		$this->db->trans_complete();

		$this->renderJson([
			'status' => $this->db->trans_status() ? 'success' : 'danger',
			'data' => $_POST
		]);
	}

	/**
	 * Show exam result.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized([PERMISSION_EXAM_VIEW, PERMISSION_EXAM_ASSESS]);

		$examExercise = $this->examExercise->getById($id);
		$exam = $this->exam->getById($examExercise['id_exam']);
		$examExerciseAnswers = $this->examExerciseAnswer->getBy(['id_exam_exercise' => $examExercise['id']]);

		$statusHistories = $this->statusHistory->getBy([
			'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
			'id_reference' => $id
		]);

		$this->render('exam_exercise/view', compact('exam', 'examExercise', 'examExerciseAnswers', 'statusHistories'));
	}
}
