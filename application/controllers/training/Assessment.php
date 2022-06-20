<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Assessment
 * @property ExamModel $exam
 * @property ExamExerciseModel $examExercise
 * @property ExamExerciseAnswerModel $examExerciseAnswer
 * @property EmployeeModel $employee
 * @property CurriculumModel $curriculum
 * @property ExerciseModel $exercise
 * @property StatusHistoryModel $statusHistory
 * @property UserModel $user
 * @property NotificationModel $notification
 * @property Exporter $exporter
 */
class Assessment extends App_Controller
{
	/**
	 * Assessment constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('TrainingModel', 'training');
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('ExamModel', 'exam');
		$this->load->model('ExamExerciseModel', 'examExercise');
		$this->load->model('ExamExerciseAnswerModel', 'examExerciseAnswer');
		$this->load->model('EmployeeModel', 'employee');
		$this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('UserModel', 'user');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('notifications/ExamAssessedNotification');
	}

	/**
	 * Show assessment index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_ASSESS);

		$filters = $_GET;

		if (!AuthorizationModel::hasPermission(PERMISSION_EXAM_MANAGE)) {
			$filters['evaluator'] = UserModel::loginData('id_employee');
		}

		if ($this->input->get('export')) {
			$exams = $this->exam->getAll($filters);
			$this->exporter->exportFromArray('Assessment', $exams);
		} else {
			$curriculums = $this->curriculum->getAll();
			$employees = $this->employee->getAll();
			$exams = $this->exam->getAllWithPagination($filters);
			foreach ($exams['data'] as &$exam) {
				$exam['exam_exercises'] = $this->examExercise->getAll(['exam' => $exam['id']]);
			}
			$this->render('assessment/index', compact('exams', 'curriculums', 'employees'));
		}
	}

	/**
	 * Assess exam exercise.
	 *
	 * @param $id
	 */
	public function edit($id)
	{
		AuthorizationModel::mustAuthorized([PERMISSION_EXAM_ASSESS]);

		$examExercise = $this->examExercise->getById($id);

		if ($examExercise['id_evaluator'] != UserModel::loginData('id_employee')) {
			flash('danger', "You are is not the evaluator that assigned to do assessment this exam", '_back');
		}

		$exam = $this->exam->getById($examExercise['id_exam']);
		$examExerciseAnswers = $this->examExerciseAnswer->getBy(['id_exam_exercise' => $examExercise['id']]);

		$this->render('assessment/edit', compact('exam', 'examExercise', 'examExerciseAnswers'));
	}

	/**
	 * Update assessment answer.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_ASSESS);

		$examExercise = $this->examExercise->getById($id);

		if ($examExercise['id_evaluator'] != UserModel::loginData('id_employee')) {
			flash('danger', "You are is not the evaluator that assigned to do assessment this exam", '_back');
		}

		$exam = $this->exam->getById($examExercise['id_exam']);

		$assessments = if_empty($this->input->post('assessments'), []);
		$description = $this->input->post('description');

		$this->db->trans_start();

		foreach ($assessments as $answerId => $assessment) {
			$this->examExerciseAnswer->update([
				'score' => if_empty($assessment['score'], 0),
				'assessment_note' => $assessment['assessment_note'],
			], $answerId);
		}

		$this->examExercise->update([
			'status' => ExamExerciseModel::STATUS_ASSESSED,
			'description' => if_empty($description, null)
		], $id);

		$this->statusHistory->create([
			'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
			'id_reference' => $id,
			'status' => ExamExerciseModel::STATUS_ASSESSED,
			'description' => $examExercise['status'] == ExamExerciseModel::STATUS_ASSESSED ? "Exam assessment is updated" : "Exam is assessed by evaluator",
			'data' => json_encode([
				'score_before' => if_empty(numerical($examExercise['score'], 1), 0),
				'score' => if_empty(numerical($this->examExercise->getById($id)['score'], 1), 0),
			])
		]);

		$this->db->trans_complete();

		if ($this->db->trans_status()) {
			$this->notification
				->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
				->to($this->user->getById($exam['id_user']))
				->send(new ExamAssessedNotification(
					$this->examExercise->getById($id)
				));
			flash('success', "Assessment {$examExercise['title']} successfully submitted");
			redirect('training/exam/view/' . $examExercise['id_exam']);
		} else {
			flash('danger', 'Save exam failed, try again or contact your trainer');
		}

		$this->edit($id);
	}
}
