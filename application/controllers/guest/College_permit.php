<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;
/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property LetterNumberModel $letterNumber
 * @property CollegePermitModel $collegePermit
 * @property CollegePermitStudentModel $collegePermitStudent
 * @property SignatureModel $signature
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class College_permit extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('LetterNumberModel', 'letterNumber');
		$this->load->model('CollegePermitModel', 'collegePermit');
		$this->load->model('CollegePermitStudentModel', 'collegePermitStudent');
		$this->load->model('SignatureModel', 'signature');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('modules/Mailer', 'mailer');
		$this->load->model('notifications/CreateCourseNotification');

		$this->setFilterMethods([
			'sort' => 'GET|PUT'
		]);
	}

	/**
	 * Show create College Permit.
	 *
	 */
	public function create()
	{
		$kaprodis = $this->lecturer->getBy(['position' => 'KAPRODI']);
		$pembimbings = $this->lecturer->getAll();
		$this->render('college_permit/create', compact('kaprodis', 'pembimbings'));
	}

	/**
	 * Save new college permit.
	 */
	public function save()
	{
		if ($this->validate()) {
			$dateNow = Carbon::now()->locale('id');
			$tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
			$email = $this->input->post('email');
			$alasan = $this->input->post('alasan');
			$tanggal = $this->input->post('tanggal');
			$mataKuliah = $this->input->post('mata_kuliah');
			$students = $this->input->post('students');
			$kaprodiId = $this->input->post('kaprodi');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$this->db->trans_start();

			$no_letter = $this->letterNumber->getLetterNumber();
			$this->letterNumber->create([
				'no_letter' => $no_letter,
			]);
			
			$letterId = $this->db->insert_id();
			$this->collegePermit->create([
				'id_kaprodi' => $kaprodiId,
				'id_letter_number' => $letterId,
				'email' => $email,
				'alasan' => $alasan,
				'tanggal' => $tanggal,
				'mata_kuliah' => $mataKuliah,
				'date' => date('Y-m-d'),
			]);
			$collegePermitId = $this->db->insert_id();

			foreach($students as $student){
				$this->collegePermitStudent->create([
					'name' => $student['nama'],
					'nim' => $student['nim'],
				]);
			}			

			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $collegePermitId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_COLLEGE_PERMIT,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);
			
			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				$options = [
					'buffer' => true,
					'view' => 'college_permit/print',
					'data' => compact('tanggalSekarang', 'alasan', 'mataKuliah', 'students',
										'tanggal', 'kaprodi', 'no_letter', 'qrCodeKaprodi'),
				];
				$output = $this->exporter->exportToPdf("Laporan Izin Kuliah.pdf", null, $options);
				$this->uploader->makeFolder('college_permit');
				file_put_contents('uploads/college_permit/Laporan Izin Kuliah.pdf', $output);
				$filepath = "uploads/college_permit/Laporan Izin Kuliah.pdf";

				//notif email
				$attachments = [];
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'college_permit' . DIRECTORY_SEPARATOR . 'Laporan Izin Kuliah.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Surat Izin Kuliah";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Surat Izin Kuliah',
                    'name' => '',
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Surat Izin Kuliah anda',
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
				redirect('guest/research-permit/create');
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
			'alasan' => 'required|max_length[100]',
			'tanggal' => 'required|max_length[100]',
			'kaprodi' => 'required|max_length[100]',
			'mata_kuliah' => 'required|max_length[100]',
		];
	}
}
