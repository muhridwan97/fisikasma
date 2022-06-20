<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Course
 * @property CourseModel $course
 * @property LessonModel $lesson
 * @property CurriculumModel $curriculum
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Letter extends App_Controller
{
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('notifications/CreateCourseNotification');

		$this->setFilterMethods([
			'sort' => 'GET|PUT'
		]);
	}

	/**
	 * Show course index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_VIEW);

		$filters = $_GET;

		if ($this->input->get('export')) {
			$courses = $this->course->getAll($filters);
			$this->exporter->exportFromArray('Courses', $courses);
		} else {
			$courses = $this->course->getAllWithPagination($filters);
			foreach ($courses['data'] as &$course) {
				$course['lessons'] = $this->lesson->getAll([
					'sort_by' => 'lesson_order',
					'course' => $course['id']
				]);
			}
			$curriculums = $this->curriculum->getAll();
			$this->render('course/index', compact('courses', 'curriculums'));
		}
	}

	/**
	 * Show course data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_VIEW);

		$course = $this->course->getById($id);
		$lessons = $this->lesson->getAll([
			'sort_by' => 'lesson_order',
			'course' => $course['id']
		]);

		$this->render('course/view', compact('course', 'lessons'));
	}

	/**
	 * Show create course.
	 *
	 * @param null $curriculumId
	 */
	public function create($curriculumId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_CREATE);

		$curriculums = $this->curriculum->getAll(['status' => CurriculumModel::STATUS_ACTIVE]);
		$selectedCurriculum = $this->curriculum->getById($curriculumId);

		$this->render('course/create', compact('curriculums', 'selectedCurriculum'));
	}

	/**
	 * Save new course data.
	 * @param null $curriculumId
	 */
	public function save($curriculumId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_CREATE);

		if ($this->validate()) {
			$curriculumId = if_empty($curriculumId, $this->input->post('curriculum'));
			$courseTitle = $this->input->post('course_title');
			$status = $this->input->post('status');
			$description = $this->input->post('description');

			$coverImage = null;
			if (!empty($_FILES['cover_image']['name'])) {
				$uploadFile = $this->uploader->setDriver('s3')->uploadTo('cover_image', [
					'destination' => 'courses/' . date('Y/m')
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
				$save = $this->course->create([
					'id_curriculum' => if_empty($curriculumId, null),
					'course_title' => $courseTitle,
					'status' => $status,
					'cover_image' => if_empty($coverImage, null),
					'description' => $description,
					'course_order' => $this->course->getNextRowOrder($curriculumId),
				]);
				$courseId = $this->db->insert_id();

				if ($save) {
					$this->notification
						->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH])
						->to(UserModel::loginData())
						->send(new CreateCourseNotification(
							$this->course->getById($courseId)
						));
					flash('success', "Course {$courseTitle} successfully created", 'syllabus/course');
				} else {
					flash('danger', 'Create course failed, try again or contact administrator');
				}
			}
		}
		$this->create();
	}

	/**
	 * Show edit course form.
	 *
	 * @param $id
	 */
	public function edit($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_EDIT);

		$course = $this->course->getById($id);
		$curriculums = $this->curriculum->getAll([
			'status' => CurriculumModel::STATUS_ACTIVE,
			'except' => $course['id_curriculum']
		]);
		$lessons = $this->lesson->getAll([
			'sort_by' => 'lesson_order',
			'course' => $course['id']
		]);

		$this->render('course/edit', compact('course', 'curriculums', 'lessons'));
	}

	/**
	 * Update data course by id.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_EDIT);

		if ($this->validate()) {
			$curriculumId = $this->input->post('curriculum');
			$courseTitle = $this->input->post('course_title');
			$status = $this->input->post('status');
			$description = $this->input->post('description');
			$lessonOrders = if_empty($this->input->post('lesson_orders'), []);

			$course = $this->course->getById($id);

			$coverImage = $course['cover_image'];
			if (!empty($_FILES['cover_image']['name'])) {
				$uploadFile = $this->uploader->setDriver('s3')->uploadTo('cover_image', [
					'destination' => 'courses/' . date('Y/m')
				]);
				if ($uploadFile) {
					$uploadedData = $this->uploader->getUploadedData();
					$coverImage = $uploadedData['uploaded_path'];
					if (!empty($course['cover_image'])) {
						$this->uploader->setDriver('s3')->delete($course['cover_image']);
					}
				} else {
					flash('danger', $this->uploader->getDisplayErrors());
				}
			} else {
				$uploadFile = true;
			}

			if ($uploadFile) {
				$this->db->trans_start();

				$this->course->update([
					'id_curriculum' => if_empty($curriculumId, null),
					'course_title' => $courseTitle,
					'status' => $status,
					'cover_image' => if_empty($coverImage, null),
					'description' => $description
				], $id);

				foreach ($lessonOrders as $lessonId => $lessonOrder) {
					$lesson = $this->lesson->getById($lessonId);
					if ($lesson['lesson_order'] != $lessonOrder) {
						$this->lesson->update([
							'lesson_order' => $lessonOrder
						], $lessonId);
					}
				}

				$this->db->trans_complete();

				if ($this->db->trans_status()) {
					flash('success', "Course {$courseTitle} successfully updated", 'syllabus/course');
				} else {
					flash('danger', "Update course failed, try again or contact administrator");
				}
			}
		}
		$this->edit($id);
	}

	/**
	 * Sort course of the curriculum.
	 *
	 * @param $curriculumId
	 */
	public function sort($curriculumId)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_EDIT);

		$curriculum = $this->curriculum->getById($curriculumId);

		if (_is_method('put')) {
			$courseOrders = if_empty($this->input->post('course_orders'), []);

			$this->db->trans_start();

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
				flash('success', "Courses of {$curriculum['curriculum_title']} successfully sorted", 'syllabus/curriculum');
			} else {
				flash('danger', "Sort courses of curriculum failed, try again or contact administrator");
			}
		} else {
			$courses = $this->course->getAll([
				'sort_by' => 'course_order',
				'curriculum' => $curriculumId
			]);
			$this->render('course/sort', compact('curriculum', 'courses'));
		}
	}

	/**
	 * Perform deleting course data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_COURSE_DELETE);

		$course = $this->course->getById($id);

		if ($this->course->delete($id, true)) {
			flash('warning', "Course {$course['course_title']} successfully deleted");
		} else {
			flash('danger', 'Delete course failed, try again or contact administrator');
		}
		redirect('syllabus/course');
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'course_title' => 'trim|required|max_length[100]',
			'curriculum' => 'required',
			'status' => 'required|in_list[ACTIVE,INACTIVE]',
			'description' => 'max_length[500]',
		];
	}
}
