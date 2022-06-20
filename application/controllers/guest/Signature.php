<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class Course
 * @property SignatureModel $signature
 * @property LecturerModel $lecturer
 * @property NotificationModel $notification
 * @property ResearchPermitModel $researchPermit
 * @property AssignmentLetterModel $assignmentLetter
 * @property InterviewPermitModel $interviewPermit
 * @property ApplicationLetterModel $applicationLetter
 * @property CourseEliminationModel $courseElimination
 * @property CollegePermitModel $collegePermit
 * @property RecommendationLetterModel $recommendationLetter
 * @property AppointmentLecturerModel $appointmentLecturer
 * @property SkripsiModel $skripsi
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Signature extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('SignatureModel', 'signature');
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('modules/Mailer', 'mailer');
		$this->load->model('notifications/CreateCourseNotification');
		$this->load->model('ResearchPermitModel', 'researchPermit');
		$this->load->model('AssignmentLetterModel', 'assignmentLetter');
		$this->load->model('InterviewPermitModel', 'interviewPermit');
		$this->load->model('ApplicationLetterModel', 'applicationLetter');
		$this->load->model('CourseEliminationModel', 'courseElimination');
		$this->load->model('CollegePermitModel', 'collegePermit');
		$this->load->model('RecommendationLetterModel', 'recommendationLetter');
		$this->load->model('AppointmentLecturerModel', 'appointmentLecturer');
		$this->load->model('SkripsiModel', 'skripsi');

		$this->setFilterMethods([
		]);
	}

	/**
     * Show Signature index page.
     */
    public function index()
    {
        $filters = get_url_param('code', 0);
        $signatures = $this->signature->getByCode($filters);
		// print_debug($signatures);
		$data['tujuan']='NOT FOUND';
		$data['signature_by']='NOT FOUND';
		if(!isset($signatures['type'])){
			$signatures['type'] = '';
		}
		switch ($signatures['type']) {
			case SignatureModel::TYPE_RESEARCH_PERMIT:
				$researchPermit = $this->researchPermit->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Izin Penelitian <br>';
				$data['tujuan'].='No Surat : '.$researchPermit['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;
			case SignatureModel::TYPE_ASSIGNMENT_LETTER:
				$assignmentLetter = $this->assignmentLetter->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Permohonan Surat Tugas <br>';
				$data['tujuan'].='No Surat : '.$assignmentLetter['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;
			case SignatureModel::TYPE_INTERVIEW_PERMIT:
				$interviewPermit = $this->interviewPermit->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Izin Wawancara <br>';
				$data['tujuan'].='No Surat : '.$interviewPermit['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;
			case SignatureModel::TYPE_APPLICATION_LETTER:
				$applicationLetter = $this->applicationLetter->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Permohonan Habis Teori <br>';
				$data['tujuan'].='Nama : '.$applicationLetter['name'].'<br>';
				$data['tujuan'].='NIM : '.$applicationLetter['nim'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;
			case SignatureModel::TYPE_COURSE_ELIMINATION:
				$courseElimination = $this->courseElimination->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Pengajuan Penghapusan Matakuliah<br>';
				$data['tujuan'].='Nama : '.$courseElimination['name'].'<br>';
				$data['tujuan'].='NIM : '.$courseElimination['nim'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;				
			case SignatureModel::TYPE_COLLEGE_PERMIT:
				$collegePermit = $this->collegePermit->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Izin Kuliah <br>';
				$data['tujuan'].='No Surat : '.$collegePermit['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;				
			case SignatureModel::TYPE_RECOMMENDATION_LETTER:
				$recommendationLetter = $this->recommendationLetter->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Rekomendasi <br>';
				$data['tujuan'].='No Surat : '.$recommendationLetter['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;			
			case SignatureModel::TYPE_APPOINTMENT_LECTURER:
				$appointmentLecturer = $this->appointmentLecturer->getById($signatures['id_reference']);
				$lecturer = $this->lecturer->getById($signatures['id_lecturer']);
				$data['tujuan']='Surat : Surat Penunjukan Pembimbing Skripsi <br>';
				$data['tujuan'].='No Surat : '.$appointmentLecturer['no_letter'].'<br>';
				$data['signature_by'] = $lecturer['name'];
				break;		
			case SignatureModel::TYPE_LOGBOOK_SKRIPSI:
				$skripsi = $this->skripsi->getBy([
					'skripsis.id' => $signatures['id_reference'],
					'logbooks.status' => LogbookModel::STATUS_VALIDATE,
					]);
				$skripsi = reset($skripsi);
				$data['tujuan']='Kartu Bimbingan Skripsi <br>';
				$data['tujuan'].='Nama : '.$skripsi['nama_mahasiswa'].'<br>';
				$data['tujuan'].='NIM : '.$skripsi['no_student'].'<br>';
				$data['tujuan'].='Total bimbingan : '.$skripsi['total_logbook'].'<br>';
				$data['signature_by'] = $skripsi['nama_pembimbing'];
				break;
			default:
				# code...
				break;
		}
        $this->render('signature/index', compact('data'));
    }
}
