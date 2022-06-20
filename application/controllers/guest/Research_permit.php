<?php

use Illuminate\Support\Str;
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;

/**
 * Class Course
 * @property LecturerModel $lecturer
 * @property CourseModel $course
 * @property SignatureModel $signature
 * @property CurriculumModel $curriculum
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 * 
 * @property ResearchPermitModel $researchPermit
 * @property LetterNumberModel $letterNumber
 */
class Research_permit extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('LecturerModel', 'lecturer');
		$this->load->model('CurriculumModel', 'curriculum');
		$this->load->model('CourseModel', 'course');
		$this->load->model('SignatureModel', 'signature');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('notifications/CreateCourseNotification');

		$this->load->model('ResearchPermitModel', 'researchPermit');
		$this->load->model('LetterNumberModel', 'letterNumber');
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
		$this->render('research_permit/create', compact('kaprodis', 'pembimbings'));
	}

	/**
	 * Save new research permit.
	 */
	public function save()
	{
		if ($this->validate()) {
			$dateNow = Carbon::now()->locale('id');
			$tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
			$terhormat = $this->input->post('terhormat');
			$email = $this->input->post('email');
			$judul = $this->input->post('judul');
			$nama = $this->input->post('nama');
			$nim = $this->input->post('nim');
			$pengambilan_data = $this->input->post('pengambilan_data');
			$metode = $this->input->post('metode');
			$kaprodiId = $this->input->post('kaprodi');
			$pembimbingId = $this->input->post('pembimbing');
			$kaprodi = $this->lecturer->getById($kaprodiId);
			$pembimbing = $this->lecturer->getById($pembimbingId);
			$this->db->trans_start();

			$no_letter = $this->letterNumber->getLetterNumber();
			$this->letterNumber->create([
				'no_letter' => $no_letter,
			]);
			
			$letterId = $this->db->insert_id();
			$this->researchPermit->create([
				'id_kaprodi' => $kaprodiId,
				'id_pembimbing' => $pembimbingId,
				'id_letter_number' => $letterId,
				'nim' => $nim,
				'name' => $nama,
				'email' => $email,
				'terhormat' => $terhormat,
				'date' => date('Y-m-d'),
				'judul' => $judul,
				'pengambilan_data' => $pengambilan_data,
				'metode' => $metode,
			]);
			$researchPermitId = $this->db->insert_id();

			$code = $this->signature->generateCode();
			$barcodeKaprodi = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $researchPermitId,
				'id_lecturer' => $kaprodiId,
				'type' => SignatureModel::TYPE_RESEARCH_PERMIT,
			]);

			$code = $this->signature->generateCode();
			$barcodePembimbing = base_url().'guest/signature?code='.$code;
			$this->signature->create([
				'code' => $code,
				'id_reference' => $researchPermitId,
				'id_lecturer' => $pembimbingId,
				'type' => SignatureModel::TYPE_RESEARCH_PERMIT,
			]);
						
            $barcode = new DNS2D();
            $barcode->setStorPath(APPPATH . "cache/");
			$qrCodeKaprodi = $barcode->getBarcodePNG($barcodeKaprodi, "QRCODE", 2, 2);
			$qrCodePembimbing = $barcode->getBarcodePNG($barcodePembimbing, "QRCODE", 2, 2);

			$this->db->trans_complete();

			if ($this->db->trans_status()) {
				$options = [
					'buffer' => true,
					'view' => 'research_permit/print',
					'data' => compact('tanggalSekarang', 'terhormat', 'judul', 'nama', 'nim', 'qrCodeKaprodi', 'qrCodePembimbing',
										'pengambilan_data', 'metode', 'kaprodi', 'pembimbing', 'no_letter'),
				];
				$output = $this->exporter->exportToPdf("Surat Izin Penelitian.pdf", null, $options);
				$this->uploader->makeFolder('research_permit');
				file_put_contents('uploads/research_permit/Surat Izin Penelitian.pdf', $output);
				$filepath = "uploads/research_permit/Surat Izin Penelitian.pdf";

				//notif email
				$uploadedPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'research_permit' . DIRECTORY_SEPARATOR . 'Surat Izin Penelitian.pdf';
				$attachments[] = [
					'source' => $uploadedPath,
				];

                $emailOptions = [
                    'attachment' => $attachments,
                ];

                $emailTo = $email;
                $emailTitle = "Surat Izin Penelitian";
                $emailTemplate = 'emails/basic';
                $emailData = [
                    'title' => 'Surat Izin Penelitian',
                    'name' => $nama,
                    'email' => $emailTo,
                    'content' => 'Berikut ini terlampir Surat Izin Penelitian anda',
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
			'terhormat' => 'required|max_length[100]',
			'judul' => 'required|max_length[100]',
			'nama' => 'required|max_length[100]',
			'nim' => 'required|max_length[100]',
			'pengambilan_data' => 'required|max_length[100]',
			'metode' => 'required|max_length[100]',
			'kaprodi' => 'required|max_length[100]',
			'pembimbing' => 'required|max_length[100]',
		];
	}
}
