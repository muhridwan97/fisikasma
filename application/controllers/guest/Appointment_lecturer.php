<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;

/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property AppointmentLecturerModel $appointmentLecturer
 * @property LetterNumberModel $letterNumber
 * @property SignatureModel $signature
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Appointment_lecturer extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('AppointmentLecturerModel', 'appointmentLecturer');
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
		$this->render('appointment_lecturer/create', compact('kaprodis', 'pembimbings'));
	}

	/**
	 * Save new application letter.
	 */
	public function save()
	{
		if ($this->validate()) {
			$dateNow = Carbon::now()->locale('id');
			$tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
			$email = $this->input->post('email');
			$nama = $this->input->post('nama');
			$nim = $this->input->post('nim');
			$semester = $this->input->post('semester');
			$judul = $this->input->post('judul');
			$tanggal = $this->input->post('tanggal');
			$kaprodiId = $this->input->post('kaprodi');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$pembimbingId = $this->input->post('pembimbing');
			$pembimbing = $this->lecturer->getById($pembimbingId);
			$this->db->trans_start();

			$no_letter = $this->letterNumber->getLetterNumber();
			$this->letterNumber->create([
				'no_letter' => $no_letter,
			]);
			$letterId = $this->db->insert_id();
			$this->appointmentLecturer->create([
				'id_kaprodi' => $kaprodiId,
				'id_pembimbing' => $pembimbingId,
				'id_letter_number' => $letterId,
				'nim' => $nim,
				'name' => $nama,
				'email' => $email,
				'semester' => $semester,
				'date' => date('Y-m-d'),
				'judul' => $judul,
				'tanggal' => $tanggal,
			]);
			$appointmentLecturerId = $this->db->insert_id();

			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $appointmentLecturerId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_APPOINTMENT_LECTURER,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);
			
			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				$dateStart = Carbon::createFromDate($tanggal)->locale('id');
				$tanggal = $dateStart->isoFormat('D MMMM YYYY');
				$options = [
					'buffer' => true,
					'view' => 'appointment_lecturer/print',
					'data' => compact('tanggalSekarang', 'semester', 'judul', 'nama', 'nim', 'qrCodeKaprodi',
										'tanggal', 'kaprodi', 'pembimbing', 'no_letter'),
				];
				$output = $this->exporter->exportToPdf("Penunjukan Pembimbing.pdf", null, $options);
				$this->uploader->makeFolder('appointment_lecturer');
				file_put_contents('uploads/appointment_lecturer/Penunjukan Pembimbing.pdf', $output);
				$filepath = "uploads/appointment_lecturer/Penunjukan Pembimbing.pdf";
				
				//notif email
				$attachments = [];
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'appointment_lecturer' . DIRECTORY_SEPARATOR . 'Penunjukan Pembimbing.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Penunjukan Pembimbing";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Penunjukan Pembimbing',
                    'name' => $nama,
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Penunjukan Pembimbing anda',
                ];
                $this->mailer->send($emailTo, $emailTitle, $emailTemplate, $emailData, $emailOptions);
				
				// Process download
				if(file_exists($filepath)) {
					$this->load->helper('download');
					force_download($filepath, NULL);
				} else {
					flash('warning', "Generate successfully but download fail, please check your email");
				}
			}
		}
		$this->create();
	}

	private function download($path){

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
			'nama' => 'required|max_length[100]',
			'nim' => 'required|max_length[100]',
			'semester' => 'required|max_length[100]',
			'judul' => 'required|max_length[500]',
			'tanggal' => 'required|max_length[100]',
			'kaprodi' => 'required',
			'pembimbing' => 'required',
		];
	}
}
