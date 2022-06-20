<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;
/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property LetterNumberModel $letterNumber
 * @property InterviewPermitModel $interviewPermit
 * @property InterviewPermitStudentModel $interviewPermitStudent
 * @property SignatureModel $signature
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Interview_permit extends App_Controller
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
		$this->load->model('InterviewPermitModel', 'interviewPermit');
		$this->load->model('InterviewPermitStudentModel', 'interviewPermitStudent');
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
	 * Show create Research Permit.
	 *
	 */
	public function create()
	{
		$kaprodis = $this->lecturer->getBy(['position' => 'KAPRODI']);
		$pembimbings = $this->lecturer->getAll();
		$this->render('interview_permit/create', compact('kaprodis', 'pembimbings'));
	}

	/**
	 * Save new interview permit.
	 */
	public function save()
	{
		if ($this->validate()) {
			$dateNow = Carbon::now()->locale('id');
			$tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
			$email = $this->input->post('email');
			$terhormat = $this->input->post('terhormat');
			$judul = $this->input->post('judul');
			$students = $this->input->post('students');
			$wawancara = $this->input->post('wawancara');
			$metode = $this->input->post('metode');
			$pembimbingId = $this->input->post('pembimbing');
			$kaprodiId = $this->input->post('kaprodi');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$pembimbing = $this->lecturer->getById($pembimbingId);
			$this->db->trans_start();

			$no_letter = $this->letterNumber->getLetterNumber();
			$this->letterNumber->create([
				'no_letter' => $no_letter,
			]);
			
			$letterId = $this->db->insert_id();
			$this->interviewPermit->create([
				'id_kaprodi' => $kaprodiId,
				'id_pembimbing' => $pembimbingId,
				'id_letter_number' => $letterId,
				'email' => $email,
				'date' => date('Y-m-d'),
				'judul' => $judul,
				'terhormat' => $terhormat,
				'wawancara' => $wawancara,
				'metode' => $metode,
			]);
			$interviewPermitId = $this->db->insert_id();

			foreach($students as $student){
				$this->interviewPermitStudent->create([
					'id_interview_permit' => $interviewPermitId,
					'name' => $student['nama'],
					'nim' => $student['nim'],
				]);
			}

			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $interviewPermitId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_INTERVIEW_PERMIT,
			]);

			$code = $this->signature->generateCode();
			$barcodePembimbing = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $interviewPermitId,
				'id_lecturer' => $pembimbingId,
				'type' => SignatureModel::TYPE_INTERVIEW_PERMIT,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);
			$qrCodePembimbing = $barcode->getBarcodePNG($barcodePembimbing, "QRCODE", 2, 2);
			
			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				$options = [
					'buffer' => true,
					'view' => 'interview_permit/print',
					'data' => compact('tanggalSekarang', 'judul', 'terhormat', 'students', 'qrCodeKaprodi', 'qrCodePembimbing',
										'wawancara', 'metode', 'kaprodi', 'pembimbing', 'no_letter'),
				];
				$output = $this->exporter->exportToPdf("Laporan Surat Izin Wawancara.pdf", null, $options);
				$this->uploader->makeFolder('interview_permit');
				file_put_contents('uploads/interview_permit/Laporan Surat Izin Wawancara.pdf', $output);
				$filepath = "uploads/interview_permit/Laporan Surat Izin Wawancara.pdf";

				//notif email
				$attachments = [];
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'interview_permit' . DIRECTORY_SEPARATOR . 'Laporan Surat Izin Wawancara.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Surat Izin Wawancara";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Surat Izin Wawancara',
                    'name' => '',
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Surat Izin Wawancara anda',
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
				redirect('guest/interview-permit/create');
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
			'terhormat' => 'required|max_length[100]',
			'judul' => 'required|max_length[100]',
			'wawancara' => 'required|max_length[100]',
			'metode' => 'required|max_length[100]',
			'pembimbing' => 'required|max_length[100]',
			'kaprodi' => 'required|max_length[100]',
		];
	}
}
