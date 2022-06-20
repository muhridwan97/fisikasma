<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Curriculum
 * @property CurriculumModel $curriculum
 * @property CourseModel $course
 * @property LessonModel $lesson
 * @property ExerciseModel $exercise
 * @property QuestionModel $question
 * @property AnswerChoicesModel $answerChoice
 * @property DepartmentModel $department
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Curriculum extends App_Controller
{
	/**
	 * Curriculum constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('QuestionModel', 'question');
		$this->load->model('AnswerChoicesModel', 'answerChoice');
		$this->load->model('CourseModel', 'course');
		$this->load->model('DepartmentModel', 'department');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('notifications/CreateCurriculumNotification');

		$this->setFilterMethods([
			'sort' => 'GET|PUT'
		]);
	}

	/**
	 * Show curriculum index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_VIEW);

		$filters = $_GET;

		if ($this->input->get('export')) {
			$curriculums = $this->curriculum->getAll($filters);
			$this->exporter->exportFromArray('Curriculums', $curriculums);
		} else {
			$curriculums = $this->curriculum->getAllWithPagination($filters);
			$departments = $this->department->getAll();

			foreach ($curriculums['data'] as &$curriculum) {
				$curriculum['courses'] = $this->course->getAll([
					'sort_by' => 'course_order',
					'curriculum' => $curriculum['id']
				]);
				foreach ($curriculum['courses'] as &$course) {
					$course['lessons'] = $this->lesson->getAll([
						'sort_by' => 'lesson_order',
						'course' => $course['id']
					]);
				}
			}
			$this->render('curriculum/index', compact('curriculums', 'departments'));
		}
	}

	/**
	 * Show curriculum data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_VIEW);

		$curriculum = $this->curriculum->getById($id);
		$courses = $this->course->getAll([
			'sort_by' => 'course_order',
			'curriculum' => $id
		]);
		foreach ($courses as &$course) {
			$course['lessons'] = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => $course['id']
			]);
		}

		$exercises = $this->exercise->getByMorph(ExerciseModel::TYPE_CURRICULUM_EXAM, $curriculum['id']);
		foreach ($exercises as &$exercise) {
			$exercise['questions'] = $this->question->getAll([
				'exercise' => $exercise['id'],
				'sort_by' => 'questions.question_order',
			]);
			foreach ($exercise['questions'] as &$question) {
				$question['answer_choices'] = $this->answerChoice->getAll([
					'question' => $question['id'],
					'sort_by' => 'answer_choices.id',
				]);
			}
		}

		$this->render('curriculum/view', compact('curriculum', 'courses', 'exercises'));
	}

	/**
	 * Show create curriculum.
	 *
	 * @param null $departmentId
	 */
	public function create($departmentId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_CREATE);

		$departments = $this->department->getAll();
		$selectedDepartment = $this->department->getById($departmentId);

		$this->render('curriculum/create', compact('departments', 'selectedDepartment'));
	}

	/**
	 * Save new curriculum data.
	 *
	 * @param null $departmentId
	 */
	public function save($departmentId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_CREATE);

		if ($this->validate()) {
			$departmentId = if_empty($departmentId, $this->input->post('department'));
			$curriculumTitle = $this->input->post('curriculum_title');
			$status = $this->input->post('status');
			$description = $this->input->post('description');

			$coverImage = null;
			if (!empty($_FILES['cover_image']['name'])) {
				$uploadFile = $this->uploader->setDriver('s3')->uploadTo('cover_image', [
					'destination' => 'curriculums/' . date('Y/m')
				]);
				if ($uploadFile) {
					$uploadedData = $this->uploader->getUploadedData();
					$coverImage = $uploadedData['uploaded_path'];
				} else {
					flash('danger', $this->uploader->getDisplayErrors());
				}
			} else {
				$uploadFile = true;
			}

			if ($uploadFile) {
				$save = $this->curriculum->create([
					'id_department' => if_empty($departmentId, null),
					'curriculum_title' => $curriculumTitle,
					'status' => $status,
					'cover_image' => if_empty($coverImage, null),
					'description' => $description,
					'curriculum_order' => $this->curriculum->getNextRowOrder($departmentId),
				]);
				$curriculumId = $this->db->insert_id();

				if ($save) {
					$this->notification
						->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH])
						->to(UserModel::loginData())
						->send(new CreateCurriculumNotification(
							$this->curriculum->getById($curriculumId)
						));
					flash('success', "Curriculum {$curriculumTitle} successfully created", 'syllabus/curriculum');
				} else {
					flash('danger', 'Create curriculum failed, try again or contact administrator');
				}
			}
		}
		$this->create();
	}

	/**
	 * Show edit curriculum form.
	 *
	 * @param $id
	 */
	public function edit($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_EDIT);

		$curriculum = $this->curriculum->getById($id);
		$courses = $this->course->getAll([
			'sort_by' => 'course_order',
			'curriculum' => $id
		]);
		$departments = $this->department->getAll();

		$this->render('curriculum/edit', compact('curriculum', 'courses', 'departments'));
	}

	/**
	 * Update data curriculum by id.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_EDIT);

		if ($this->validate()) {
			$departmentId = $this->input->post('department');
			$curriculumTitle = $this->input->post('curriculum_title');
			$status = $this->input->post('status');
			$description = $this->input->post('description');
			$courseOrders = if_empty($this->input->post('course_orders'), []);

			$curriculum = $this->curriculum->getById($id);

			$coverImage = $curriculum['cover_image'];
			if (!empty($_FILES['cover_image']['name'])) {
				$uploadFile = $this->uploader->setDriver('s3')->uploadTo('cover_image', [
					'destination' => 'curriculums/' . date('Y/m')
				]);
				if ($uploadFile) {
					$uploadedData = $this->uploader->getUploadedData();
					$coverImage = $uploadedData['uploaded_path'];
					if (!empty($curriculum['cover_image'])) {
						$this->uploader->setDriver('s3')->delete($curriculum['cover_image']);
					}
				} else {
					flash('danger', $this->uploader->getDisplayErrors());
				}
			} else {
				$uploadFile = true;
			}

			if ($uploadFile) {
				$this->db->trans_start();

				$this->curriculum->update([
					'id_department' => if_empty($departmentId, null),
					'curriculum_title' => $curriculumTitle,
					'cover_image' => if_empty($coverImage, null),
					'status' => $status,
					'description' => $description,
				], $id);

				foreach ($courseOrders as $courseId => $courseOrder) {
					$course = $this->course->getById($courseId);
					if ($course['course_order'] != $courseOrder) {
						$this->course->update([
							'course_order' => $courseOrder
						], $courseId);
					}
				}

				$this->db->trans_complete();

				if ($this->db->trans_status()) {
					flash('success', "Curriculum {$curriculumTitle} successfully updated", 'syllabus/curriculum');
				} else {
					flash('danger', "Update curriculum failed, try again or contact administrator");
				}
			}
		}
		$this->edit($id);
	}

	/**
	 * Sort curriculum of the department.
	 *
	 * @param $departmentId
	 */
	public function sort($departmentId)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_EDIT);

		$department = $this->department->getById($departmentId);

		if (_is_method('put')) {
			$curriculumOrders = if_empty($this->input->post('curriculum_orders'), []);

			$this->db->trans_start();

			foreach ($curriculumOrders as $curriculumId => $curriculumOrder) {
				$curriculum = $this->curriculum->getById($curriculumId);
				if ($curriculum['curriculum_orders'] != $curriculumOrder) {
					$this->curriculum->update([
						'curriculum_order' => $curriculumOrder
					], $curriculumId);
				}
			}

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				flash('success', "Curriculum of {$department['department']} successfully sorted", 'master/department');
			} else {
				flash('danger', "Sort curriculum of department failed, try again or contact administrator");
			}
		} else {
			$curriculums = $this->curriculum->getAll([
				'sort_by' => 'curriculum_order',
				'department' => $departmentId
			]);
			$this->render('curriculum/sort', compact('department', 'curriculums'));
		}
	}

	/**
	 * Perform deleting curriculum data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_CURRICULUM_DELETE);

		$curriculum = $this->curriculum->getById($id);

		if ($this->curriculum->delete($id, true)) {
			flash('warning', "Curriculum {$curriculum['curriculum_title']} successfully deleted");
		} else {
			flash('danger', 'Delete curriculum failed, try again or contact administrator');
		}
		redirect('syllabus/curriculum');
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'curriculum_title' => 'trim|required|max_length[100]',
			'department' => 'required',
			'status' => 'required|in_list[ACTIVE,INACTIVE]',
			'description' => 'max_length[500]',
		];
	}
}
