<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Lesson
 * @property LessonModel $lesson
 * @property ExerciseModel $exercise
 * @property CourseModel $course
 * @property CurriculumModel $curriculum
 * @property QuestionModel $question
 * @property AnswerChoicesModel $answerChoice
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 */
class Lesson extends App_Controller
{
	/**
	 * Lesson constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('LessonModel', 'lesson');
		$this->load->model('ExerciseModel', 'exercise');
		$this->load->model('QuestionModel', 'question');
		$this->load->model('AnswerChoicesModel', 'answerChoice');
		$this->load->model('CourseModel', 'course');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('notifications/CreateLessonNotification');

		$this->setFilterMethods([
			'sort' => 'GET|PUT'
		]);
	}

	/**
	 * Show lesson index page.
	 */
	public function index()
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_VIEW);

		$filters = $_GET;

		if ($this->input->get('export')) {
			$lessons = $this->lesson->getAll($filters);
			$this->exporter->exportFromArray('Lessons', $lessons);
		} else {
			$lessons = $this->lesson->getAllWithPagination($filters);
			$curriculums = $this->curriculum->getAll();
			$courses = $this->course->getAll();
			$this->render('lesson/index', compact('lessons', 'curriculums', 'courses'));
		}
	}

	/**
	 * Show lesson data.
	 *
	 * @param $id
	 */
	public function view($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_VIEW);

		$lesson = $this->lesson->getById($id);
		$exercises = $this->exercise->getByMorph(ExerciseModel::TYPE_LESSON_EXERCISE, $lesson['id']);
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

		$this->render('lesson/view', compact('lesson', 'exercises'));
	}

	/**
	 * Show create lesson.
	 *
	 * @param null $courseId
	 */
	public function create($courseId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_CREATE);

		$courses = $this->course->getAll(['status' => CourseModel::STATUS_ACTIVE]);
		$selectedCourse = $this->course->getById($courseId);

		$this->render('lesson/create', compact('courses', 'selectedCourse'));
	}

	/**
	 * Save new lesson data.
	 *
	 * @param null $courseId
	 */
	public function save($courseId = null)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_CREATE);

		if ($this->validate()) {
			$courseId = if_empty($courseId, $this->input->post('course'));
			$courseTitle = $this->input->post('lesson_title');
			$description = $this->input->post('description');
			$fileInput = $this->input->post('file_input');

			$storeTo = 'lessons/' . date('Y/m/') . $fileInput;
			$mime = mime_content_type(Uploader::TEMP_PATH . '/' . $fileInput);
			$this->uploader->setDriver('s3')->move('temp/' . $fileInput, $storeTo);

			$save = $this->lesson->create([
				'id_course' => if_empty($courseId, null),
				'lesson_title' => $courseTitle,
				'description' => $description,
				'source' => $storeTo,
				'mime' => $mime,
				'lesson_order' => $this->lesson->getNextRowOrder($courseId),
			]);
			$lessonId = $this->db->insert_id();

			if ($save) {
				$this->notification
					->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH])
					->to(UserModel::loginData())
					->send(new CreateLessonNotification(
						$this->lesson->getById($lessonId)
					));
				flash('success', "Lesson {$courseTitle} successfully created", 'syllabus/lesson');
			} else {
				flash('danger', 'Create lesson failed, try again or contact administrator');
			}
		}
		$this->create();
	}

	/**
	 * Show edit lesson form.
	 *
	 * @param $id
	 */
	public function edit($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_EDIT);

		$lesson = $this->lesson->getById($id);
		$courses = $this->course->getAll([
			'status' => CurriculumModel::STATUS_ACTIVE,
			'except' => $lesson['id_course']
		]);

		$this->render('lesson/edit', compact('lesson', 'courses'));
	}

	/**
	 * Update data lesson by id.
	 *
	 * @param $id
	 */
	public function update($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_EDIT);

		if ($this->validate()) {
			$courseId = $this->input->post('course');
			$lessonTitle = $this->input->post('lesson_title');
			$description = $this->input->post('description');
			$fileInput = $this->input->post('file_input');

			$lesson = $this->lesson->getById($id);

			$storeTo = $lesson['source'];
			$mime = $lesson['mime'];
			if ($lesson['source'] != $fileInput) {
				$storeTo = 'lessons/' . date('Y/m/') . $fileInput;
				$mime = mime_content_type(Uploader::TEMP_PATH . '/' . $fileInput);
				if ($this->uploader->setDriver('s3')->move('temp/' . $fileInput, $storeTo)) {
					if (!empty($lesson['source'])) {
						$this->uploader->setDriver('s3')->delete($lesson['source']);
					}
				}
			}

			$update = $this->lesson->update([
				'id_course' => $courseId,
				'lesson_title' => $lessonTitle,
				'description' => $description,
				'source' => $storeTo,
				'mime' => $mime,
			], $id);

			if ($update) {
				flash('success', "Lesson {$lessonTitle} successfully updated", 'syllabus/lesson');
			} else {
				flash('danger', "Update lesson failed, try again or contact administrator");
			}
		}
		$this->edit($id);
	}

	/**
	 * Sort lesson of the course.
	 *
	 * @param $courseId
	 */
	public function sort($courseId)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_EDIT);

		$course = $this->course->getById($courseId);

		if (_is_method('put')) {
			$lessonOrders = if_empty($this->input->post('lesson_orders'), []);

			$this->db->trans_start();

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
				flash('success', "Lessons of {$course['course_title']} successfully sorted", 'syllabus/course');
			} else {
				flash('danger', "Sort lessons of course failed, try again or contact administrator");
			}
		} else {
			$lessons = $this->lesson->getAll([
				'sort_by' => 'lesson_order',
				'course' => $courseId
			]);
			$this->render('lesson/sort', compact('course', 'lessons'));
		}
	}

	/**
	 * Perform deleting lesson data.
	 *
	 * @param $id
	 */
	public function delete($id)
	{
		AuthorizationModel::mustAuthorized(PERMISSION_LESSON_DELETE);

		$lesson = $this->lesson->getById($id);

		if ($this->lesson->delete($id, true)) {
			flash('warning', "Lesson {$lesson['lesson_title']} successfully deleted");
		} else {
			flash('danger', 'Delete lesson failed, try again or contact administrator');
		}
		redirect('syllabus/lesson');
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'lesson_title' => 'trim|required|max_length[100]',
			'course' => 'required',
			'file_input' => 'required',
			'description' => 'max_length[500]',
		];
	}
}
