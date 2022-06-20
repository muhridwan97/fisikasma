<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property LecturerModel $lecturer
 * @property StudentModel $student
 * @property LessonModel $lesson
 * @property LetterNumberModel $letterNumber
 * @property ExamExerciseModel $examExercise
 * Class Dashboard
 */
class Dashboard extends App_Controller
{
	/**
	 * Dashboard constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('StudentModel', 'student');
	}

	/**
	 * Show dashboard page.
	 */
	public function index()
	{
		$data = [
			'totalLecturer' => $this->lecturer->getBy([], 'COUNT'),
			'totalStudent' => $this->student->getBy([], 'COUNT'),
			'totalLesson' => [],
			'totalLetterNumber' => [],
		];

		$data['latestExams'] =  [];
		$data['activeTrainings'] = [];

		$this->render('dashboard/index', $data);
	}
}
