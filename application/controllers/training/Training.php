<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Training
 * @property TrainingModel $training
 * @property ExamModel $exam
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
class Training extends App_Controller
{
	/**
	 * Training constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('TrainingModel', 'training');
		$this->load->model('ExamModel', 'exam');
		$this->load->model('EmployeeModel', 'employee');
		$this->load->model('UserModel', 'user');
		$this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('notifications/CreateTrainingNotification');
		$this->load->model('notifications/DeactivateTrainingNotification');

		$this->setFilterMethods([
			'inactive' => 'POST|PUT'
		]);
	}

	/**
	 * Show training index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_VIEW);

		$filters = $_GET;

		if (!AuthorizationModel::hasPermission(PERMISSION_TRAINING_MANAGE)) {
			$filters['employee'] = UserModel::loginData('id_employee');
		}

		if ($this->input->get('export')) {
			$trainings = $this->training->getAll($filters);
			$this->exporter->exportFromArray('Trainings', $trainings);
		} else {
			$curriculums = $this->curriculum->getAll();
			$employees = $this->employee->getAll();
			$trainings = $this->training->getAllWithPagination($filters);
			$this->render('training/index', compact('trainings', 'curriculums', 'employees'));
		}
	}

	/**
	 * Show training data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_VIEW);

		$training = $this->training->getById($id);
		$curriculum = $this->curriculum->getById($training['id_curriculum']);

		if ($curriculum['status'] == CurriculumModel::STATUS_INACTIVE) {
			flash('danger', 'Curriculum is inactive', '_back');
		}

		$statusHistories = $this->statusHistory->getBy([
			'type' => StatusHistoryModel::TYPE_TRAINING,
			'id_reference' => $id
		]);
		$courses = $this->course->getAll([
			'status' => CourseModel::STATUS_ACTIVE,
			'sort_by' => 'course_order',
			'curriculum' => $curriculum['id']
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

		$this->render('training/view', compact('training', 'statusHistories', 'courses'));
	}

	/**
	 * Show create training.
	 */
	public function create()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_CREATE);

		$curriculums = $this->curriculum->getAll(['status' => CurriculumModel::STATUS_ACTIVE]);
		$employees = $this->employee->getAll(['status' => EmployeeModel::STATUS_ACTIVE]);

		$this->render('training/create', compact('curriculums', 'employees'));
	}

	/**
	 * Save new training data.
	 */
	public function save()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_CREATE);

		if ($this->validate()) {
			$curriculumId = $this->input->post('curriculum');
			$employeeId = $this->input->post('employee');
			$description = $this->input->post('description');

			$curriculum = $this->curriculum->getById($curriculumId);
			$employee = $this->employee->getById($employeeId);

			$this->db->trans_start();

			$this->training->create([
				'id_curriculum' => $curriculumId,
				'id_employee' => $employeeId,
				'status' => TrainingModel::STATUS_ACTIVE,
				'description' => $description,
			]);
			$trainingId = $this->db->insert_id();

			$this->statusHistory->create([
				'type' => StatusHistoryModel::TYPE_TRAINING,
				'id_reference' => $trainingId,
				'status' => TrainingModel::STATUS_ACTIVE,
				'description' => "Training {$curriculum['curriculum_title']} for {$employee['name']} is created",
			]);

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				if (!empty($employee['id_user'])) {
					$this->notification
						->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
						->to($this->user->getById($employee['id_user']))
						->send(new CreateTrainingNotification(
							$this->training->getById($trainingId)
						));
				}
				flash('success', "Training {$curriculum['curriculum_title']} for {$employee['name']} successfully created", 'training/class');
			} else {
				flash('danger', 'Create training failed, try again or contact administrator');
			}
		}
		$this->create();
	}

	/**
	 * Set inactive training.
	 *
	 * @param $id
	 */
	public function inactive($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_EDIT);

		$message = $this->input->post('message');

		$training = $this->training->getById($id);

		$this->db->trans_start();

		$this->training->update([
			'status' => TrainingModel::STATUS_INACTIVE,
		], $id);

		$this->statusHistory->create([
			'type' => StatusHistoryModel::TYPE_TRAINING,
			'id_reference' => $id,
			'status' => TrainingModel::STATUS_INACTIVE,
			'description' => if_empty($message, "Training is deactivated"),
		]);

		$this->db->trans_complete();

		if ($this->db->trans_status()) {
			$this->notification
				->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
				->to($this->user->getById($training['id_user']))
				->send(new DeactivateTrainingNotification($training, $message));

			flash('warning', "Training {$training['curriculum_title']} for employee {$training['employee_name']} is successfully deactivated");
		} else {
			flash('danger', "Deactivate training failed, try again or contact administrator");
		}
		redirect('training/class');
	}

	/**
	 * Perform deleting training data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_TRAINING_DELETE);

		$training = $this->training->getById($id);

		if ($this->training->delete($id, true)) {
			flash('warning', "Training {$training['curriculum_title']} for {$training['employee_name']} successfully deleted");
		} else {
			flash('danger', 'Delete training failed, try again or contact administrator');
		}
		redirect('training/class');
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'employee' => 'trim|required|max_length[100]',
			'curriculum' => [
				'trim', 'required',
				['training_exists', function ($curriculumId) {
					$this->form_validation->set_message('training_exists', 'Training is already active for current employee');
					return empty($this->training->getAll([
						'curriculum' => $curriculumId,
						'employee' => $this->input->post('employee'),
						'status' => TrainingModel::STATUS_ACTIVE,
					]));
				}],
				['exam_active', function ($curriculumId) {
					$this->form_validation->set_message('exam_active', 'Curriculum exam is active for current employee');
					return empty($this->exam->getAll([
						'curriculum' => $curriculumId,
						'employee' => $this->input->post('employee'),
						'status' => TrainingModel::STATUS_ACTIVE,
					]));
				}]
			],
			'description' => 'max_length[500]',
		];
	}
}
