<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Exam
 * @property TrainingModel $training
 * @property ExamModel $exam
 * @property ExamExerciseModel $examExercise
 * @property EmployeeModel $employee
 * @property CurriculumModel $curriculum
 * @property CourseModel $course
 * @property LessonModel $lesson
 * @property ExerciseModel $exercise
 * @property UserModel $user
 * @property StatusHistoryModel $statusHistory
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Exam extends App_Controller
{
	/**
	 * Exam constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('TrainingModel', 'training');
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('ExamModel', 'exam');
		$this->load->model('ExamExerciseModel', 'examExercise');
		$this->load->model('EmployeeModel', 'employee');
		$this->load->model('UserModel', 'user');
		$this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('notifications/CreateExamNotification');
		$this->load->model('notifications/DeactivateTrainingNotification');

		$this->setFilterMethods([
			'inactive' => 'POST|PUT'
		]);
	}

	/**
	 * Show exam index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_VIEW);

		$filters = $_GET;

		if (!AuthorizationModel::hasPermission(PERMISSION_EXAM_MANAGE)) {
			$filters['employee'] = UserModel::loginData('id_employee');
		}

		if ($this->input->get('export')) {
			$exams = $this->exam->getAll($filters);
			$this->exporter->exportFromArray('Exams', $exams);
		} else {
			$curriculums = $this->curriculum->getAll();
			$employees = $this->employee->getAll();
			$exams = $this->exam->getAllWithPagination($filters);
			$this->render('exam/index', compact('exams', 'curriculums', 'employees'));
		}
	}

	/**
	 * Show exam data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized([PERMISSION_EXAM_VIEW, PERMISSION_EXAM_ASSESS]);

		$exam = $this->exam->getById($id);

		if (!AuthorizationModel::hasPermission(PERMISSION_EXAM_MANAGE)) {
			if ($exam['id_evaluator'] != UserModel::loginData('id_employee') && $exam['id_employee'] != UserModel::loginData('id_employee')) {
				flash('danger', "You are is not the evaluator or employee that assigned to do assessment this exam", '_back');
			}
		}

		$curriculum = $this->curriculum->getById($exam['id_curriculum']);
		$examExercises = $this->examExercise->getAll(['exam' => $exam['id']]);

		$statusHistories = $this->statusHistory->getBy([
			'type' => StatusHistoryModel::TYPE_EXAM,
			'id_reference' => $id
		]);

		// check if outstanding exams is started and timeout
		// force to finished or assessed
		foreach ($examExercises as $examExercise) {
			if ($examExercise['status'] == ExamExerciseModel::STATUS_STARTED) {
				$timeLeft = $this->examExercise->getQuizTimeLeft($examExercise);
				$timeLimit = $this->examExercise->getQuizTimeLimit($examExercise);
				if ($timeLeft < '00:00:00') {
					$this->examExercise->update([
						'finished_at' => $timeLimit,
						'status' => $examExercise['category'] == ExerciseModel::CATEGORY_CHOICES
							? ExamExerciseModel::STATUS_ASSESSED
							: ExamExerciseModel::STATUS_FINISHED
					], $examExercise['id']);
				}
			}
		}

		$this->render('exam/view', compact('exam', 'statusHistories', 'curriculum', 'examExercises'));
	}

	/**
	 * Show create exam.
	 */
	public function create()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_CREATE);

		$curriculums = $this->curriculum->getAll(['status' => CurriculumModel::STATUS_ACTIVE]);
		$employees = $this->employee->getAll(['status' => EmployeeModel::STATUS_ACTIVE]);

		$this->render('exam/create', compact('curriculums', 'employees'));
	}

	/**
	 * Save new exam data.
	 */
	public function save()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_CREATE);

		if ($this->validate()) {
			$curriculumId = $this->input->post('curriculum');
			$employeeId = $this->input->post('employee');
			$evaluatorId = $this->input->post('evaluator');
			$description = $this->input->post('description');

			$curriculum = $this->curriculum->getById($curriculumId);
			$employee = $this->employee->getById($employeeId);

			$this->db->trans_start();

			$this->exam->create([
				'id_curriculum' => $curriculumId,
				'id_employee' => $employeeId,
				'id_evaluator' => if_empty($evaluatorId, null),
				'status' => ExamModel::STATUS_ACTIVE,
				'description' => $description,
			]);
			$examId = $this->db->insert_id();

			$exercises = $this->exercise->getByMorph(ExerciseModel::TYPE_CURRICULUM_EXAM, $curriculum['id']);
			foreach ($exercises as $exercise) {
				$this->examExercise->create([
					'id_exam' => $examId,
					'id_exercise' => $exercise['id'],
					'title' => $exercise['exercise_title'],
					'status' => ExamExerciseModel::STATUS_PENDING,
				]);
				$examExerciseId = $this->db->insert_id();

				$this->statusHistory->create([
					'type' => StatusHistoryModel::TYPE_EXAM_EXERCISE,
					'id_reference' => $examExerciseId,
					'status' => ExamExerciseModel::STATUS_PENDING,
					'description' => "Exam is ready to take",
				]);
			}

			$this->statusHistory->create([
				'type' => StatusHistoryModel::TYPE_EXAM,
				'id_reference' => $examId,
				'status' => ExamModel::STATUS_ACTIVE,
				'description' => "Exam {$curriculum['curriculum_title']} for {$employee['name']} is created",
			]);

			$activeTrainings = $this->training->getAll([
				'status' => TrainingModel::STATUS_ACTIVE,
				'employee' => $employeeId,
				'curriculum' => $curriculumId,
			]);

			foreach ($activeTrainings as $activeTraining) {
				$this->training->update([
					'status' => TrainingModel::STATUS_INACTIVE
				], $activeTraining['id']);

				$deactivateMessage = "Training deactivated due to exam";
				$this->statusHistory->create([
					'type' => StatusHistoryModel::TYPE_TRAINING,
					'id_reference' => $activeTraining['id'],
					'status' => ExamModel::STATUS_INACTIVE,
					'description' => $deactivateMessage,
				]);
				$this->notification
					->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
					->to($this->user->getById($activeTraining['id_user']))
					->send(new DeactivateTrainingNotification($activeTraining, $deactivateMessage));
			}

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				if (!empty($employee['id_user'])) {
					$this->notification
						->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
						->to($this->user->getById($employee['id_user']))
						->send(new CreateExamNotification(
							$this->exam->getById($examId)
						));
				}
				flash('success', "Exam {$curriculum['curriculum_title']} for {$employee['name']} successfully created", 'training/exam');
			} else {
				flash('danger', 'Create exam failed, try again or contact administrator');
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
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_EDIT);

		$exam = $this->exam->getById($id);
		$employees = $this->employee->getAll(['status' => EmployeeModel::STATUS_ACTIVE]);

		$this->render('exam/edit', compact('exam', 'employees'));
	}

	/**
	 * Update data exam by id.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_EDIT);

		if ($this->validate($this->_validation_rules($id))) {
			$evaluatorId = $this->input->post('evaluator');
			$description = $this->input->post('description');

			$exam = $this->exam->getById($id);

			$this->db->trans_start();

			$this->exam->update([
				'id_evaluator' => if_empty($evaluatorId, null),
				'description' => $description,
			], $id);

			if ($exam['id_evaluator'] != $evaluatorId) {
				$evaluator = $this->employee->getById($evaluatorId);
				$this->statusHistory->create([
					'type' => StatusHistoryModel::TYPE_EXAM,
					'id_reference' => $id,
					'status' => $exam['status'],
					'description' => "Evaluator changed from " . if_empty($exam['evaluator_name'], 'Trainer') . " to " . if_empty($evaluator['name'], 'Trainer') . " is created",
				]);
			}

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				flash('success', "Exam {$exam['curriculum_title']} successfully updated", 'training/exam');
			} else {
				flash('danger', "Update exam failed, try again or contact administrator");
			}
		}
		$this->edit($id);
	}

	/**
	 * Set inactive exam.
	 *
	 * @param $id
	 */
	public function inactive($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_EDIT);

		$message = $this->input->post('message');

		$exam = $this->exam->getById($id);

		$this->db->trans_start();

		$this->exam->update([
			'status' => ExamModel::STATUS_INACTIVE,
		], $id);

		$this->statusHistory->create([
			'type' => StatusHistoryModel::TYPE_EXAM,
			'id_reference' => $id,
			'status' => ExamModel::STATUS_INACTIVE,
			'description' => if_empty($message, "Exam is deactivated"),
		]);

		$this->db->trans_complete();

		if ($this->db->trans_status()) {
			flash('warning', "Exam {$exam['curriculum_title']} for employee {$exam['employee_name']} is successfully deactivated");
		} else {
			flash('danger', "Deactivate exam failed, try again or contact administrator");
		}
		redirect('training/exam');
	}

	/**
	 * Perform deleting exam data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_EXAM_DELETE);

		$exam = $this->exam->getById($id);

		if ($this->exam->delete($id, true)) {
			flash('warning', "Exam {$exam['curriculum_title']} for {$exam['employee_name']} successfully deleted");
		} else {
			flash('danger', 'Delete exam failed, try again or contact administrator');
		}
		redirect('training/exam');
	}

	/**
	 * Return general validation rules.
	 *
	 * @param mixed ...$params
	 * @return array
	 */
	protected function _validation_rules(...$params)
	{
		$id = isset($params[0]) ? $params[0] : 0;

		return [
			'employee' => 'trim|required|max_length[100]',
			'evaluator' => [
				'trim', 'required', ['evaluator_invalid', function ($evaluatorId) {
					$this->form_validation->set_message('evaluator_invalid', 'Evaluator cannot be same with employee');
					return $evaluatorId != $this->input->post('employee');
				}]
			],
			'curriculum' => [
				'trim', 'required',
				['exam_exists', function ($curriculumId) use ($id) {
					$this->form_validation->set_message('exam_exists', 'Exam is already active for current employee');
					return empty($this->exam->getBy([
						'exams.id!=' => $id,
						'exams.id_curriculum' => $curriculumId,
						'exams.id_employee' => $this->input->post('employee'),
						'exams.status' => ExamModel::STATUS_ACTIVE,
					]));
				}],
				['exam_empty', function ($curriculumId) {
					$this->form_validation->set_message('exam_empty', 'Curriculum has no exam data');
					return !empty($this->exercise->getByMorph(ExerciseModel::TYPE_CURRICULUM_EXAM, $curriculumId));
				}]
			],
			'description' => 'max_length[500]',
		];
	}
}
