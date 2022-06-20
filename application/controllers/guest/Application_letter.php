<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;

/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property ApplicationLetterModel $applicationLetter
 * @property SignatureModel $signature
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Application_letter extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('ApplicationLetterModel', 'applicationLetter');
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
		$this->render('application_letter/create', compact('kaprodis', 'pembimbings'));
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
			$alamat = $this->input->post('alamat');
			$noTelepon = $this->input->post('no_telepon');
			$kaprodiId = $this->input->post('kaprodi');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$this->db->trans_start();
			
			$letterId = $this->db->insert_id();
			$this->applicationLetter->create([
				'id_kaprodi' => $kaprodiId,
				'nim' => $nim,
				'name' => $nama,
				'email' => $email,
				'semester' => $semester,
				'date' => date('Y-m-d'),
				'alamat' => $alamat,
				'no_telepon' => $noTelepon,
			]);
			$applicationLetterId = $this->db->insert_id();

			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $applicationLetterId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_APPLICATION_LETTER,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);
			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				$options = [
					'buffer' => true,
					'view' => 'application_letter/print',
					'data' => compact('tanggalSekarang', 'semester', 'alamat', 'nama', 'nim',
										'noTelepon', 'kaprodi', 'qrCodeKaprodi'),
				];
				$output = $this->exporter->exportToPdf("Surat teori.pdf", null, $options);
				$this->uploader->makeFolder('application_letter');
				file_put_contents('uploads/application_letter/Surat teori.pdf', $output);
				$filepath = "uploads/application_letter/Surat teori.pdf";
				
				//notif email
				$attachments = [];
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'application_letter' . DIRECTORY_SEPARATOR . 'Surat teori.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Surat teori";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Surat teori',
                    'name' => $nama,
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Surat teori anda',
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
				redirect('guest/application_letter/create');
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
			'nama' => 'required|max_length[100]',
			'nim' => 'required|max_length[100]',
			'semester' => 'required|max_length[100]',
			'alamat' => 'required|max_length[500]',
			'no_telepon' => 'required|max_length[100]',
			'kaprodi' => 'required',
		];
	}
}
