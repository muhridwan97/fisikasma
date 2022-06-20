<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;
/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property AssignmentLetterModel $assignmentLetter
 * @property AssignmentLetterStudentModel $assignmentLetterStudent
 * @property LetterNumberModel $letterNumber
 * @property SignatureModel $signature
 * @property CourseModel $course
 * @property LessonModel $lesson
 * @property CurriculumModel $curriculum
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Assignment_letter extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('AssignmentLetterModel', 'assignmentLetter');
		$this->load->model('AssignmentLetterStudentModel', 'assignmentLetterStudent');
		$this->load->model('LetterNumberModel', 'letterNumber');
		$this->load->model('SignatureModel', 'signature');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('modules/Mailer', 'mailer');

		$this->setFilterMethods([
			'sort' => 'GET|PUT'
		]);
	}

	/**
	 * Show create Research Permit.
	 *
	 */
	public function create()
	{
		$kaprodis = $this->lecturer->getBy(['position' => 'KAPRODI']);
		$pembimbings = $this->lecturer->getAll();
		$this->render('assignment_letter/create', compact('kaprodis', 'pembimbings'));
	}

	/**
	 * Save new assignment letter.
	 */
	public function save()
	{
		if ($this->validate()) {
			$dateNow = Carbon::now()->locale('id');
			$tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
			$email = $this->input->post('email');
			$judul = $this->input->post('judul');
			$students = $this->input->post('students');
			$kaprodiId = $this->input->post('kaprodi');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$tujuan = $this->input->post('tujuan');
			$tanggal_mulai = $this->input->post('tanggal_mulai');
			$tanggal_selesai = $this->input->post('tanggal_selesai');
			$tempat = $this->input->post('tempat');
			$penyelenggara = $this->input->post('penyelenggara');
			$this->db->trans_start();

			$no_letter = $this->letterNumber->getLetterNumber();
			$this->letterNumber->create([
				'no_letter' => $no_letter,
			]);
			
			$letterId = $this->db->insert_id();
			$this->assignmentLetter->create([
				'id_kaprodi' => $kaprodiId,
				'id_letter_number' => $letterId,
				'email' => $email,
				'date' => date('Y-m-d'),
				'judul' => $judul,
				'tujuan' => $tujuan,
				'tanggal_mulai' => $tanggal_mulai,
				'tanggal_selesai' => $tanggal_selesai,
				'tempat' => $tempat,
				'penyelenggara' => $penyelenggara,
			]);
			$assignmentLetterId = $this->db->insert_id();

			foreach($students as $student){
				$this->assignmentLetterStudent->create([
					'name' => $student['nama'],
					'nip' => $student['nip'],
					'jabatan' => $student['jabatan'],
				]);
			}
			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $assignmentLetterId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_ASSIGNMENT_LETTER,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);

			$this->db->trans_complete();
			$tanggal_pelaksana = '';
			if($tanggal_mulai == $tanggal_selesai){
				$dateStart = Carbon::createFromDate($tanggal_mulai)->locale('id');
				$tanggalMulai = $dateStart->isoFormat('D MMMM YYYY');
				$tanggal_pelaksana = $tanggalMulai;
			}else{
				if(date('Y',strtotime($tanggal_mulai)) != date('Y',strtotime($tanggal_selesai))){
					$dateStart = Carbon::createFromDate($tanggal_mulai)->locale('id');
					$tanggalMulai = $dateStart->isoFormat('D MMMM YYYY');
					$dateEnd = Carbon::createFromDate($tanggal_selesai)->locale('id');
					$tanggalSelesai = $dateEnd->isoFormat('D MMMM YYYY');
					$tanggal_pelaksana = $tanggalMulai.' - '. $tanggalSelesai;
				}else if(date('m',strtotime($tanggal_mulai)) != date('m',strtotime($tanggal_selesai))){
					$dateStart = Carbon::createFromDate($tanggal_mulai)->locale('id');
					$tanggalMulai = $dateStart->isoFormat('D MMMM');
					$dateEnd = Carbon::createFromDate($tanggal_selesai)->locale('id');
					$tanggalSelesai = $dateEnd->isoFormat('D MMMM');
					$tanggal_pelaksana = $tanggalMulai.' - '. $tanggalSelesai. ' '.date('Y',strtotime($tanggal_mulai));
				}else if(date('d',strtotime($tanggal_mulai)) != date('d',strtotime($tanggal_selesai))){
					$dateStart = Carbon::createFromDate($tanggal_mulai)->locale('id');
					$tanggalMulai = $dateStart->isoFormat('MMMM YYYY');
					$tanggal_pelaksana = date('d',strtotime($tanggal_mulai)).' - '. date('d',strtotime($tanggal_selesai)). ' '.$tanggalMulai;
				}
			}

			if ($this->db->trans_status()) {
				$options = [
					'buffer' => true,
					'view' => 'assignment_letter/print',
					'data' => compact('tanggalSekarang', 'judul', 'students', 'tujuan', 'penyelenggara',
										'kaprodi', 'no_letter', 'tanggal_pelaksana', 'tempat', 'qrCodeKaprodi'),
				];
				$output = $this->exporter->exportToPdf("Surat Permohonan Tugas.pdf", null, $options);
				$this->uploader->makeFolder('assignment_letter');
				file_put_contents('uploads/assignment_letter/Surat Permohonan Tugas.pdf', $output);
				$filepath = "uploads/assignment_letter/Surat Permohonan Tugas.pdf";

				//notif email
				$attachments = [];
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'assignment_letter' . DIRECTORY_SEPARATOR . 'Surat Permohonan Tugas.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Surat Permohonan Tugas";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Surat Permohonan Tugas',
                    'name' => '',
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Surat Permohonan Tugas anda',
                ];
                $this->mailer->send($emailTo, $emailTitle, $emailTemplate, $emailData, $emailOptions);
				// Process download
				if(file_exists($filepath)) {
					header('Content-Description: File Transfer');
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($filepath));
					flush(); // Flush system output buffer
					readfile($filepath);
					die();
				} else {
					flash('warning', "Generate successfully but download fail, please check your email");
				}
				redirect('guest/assignment-letter/create');
			}
		}
		$this->create();
	}

	/**
	 * Return general validation rules.
	 *
	 * @return array
	 */
	protected function _validation_rules()
	{
		return [
			'email' => 'trim|required|max_length[100]|valid_email',
			'judul' => 'required|max_length[100]',
			'kaprodi' => 'required|max_length[100]',
		];
	}
}
