<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Carbon\Carbon;
use Milon\Barcode\DNS2D;
/**
 * Class Skripsi
 * @property LogbookModel $logbook
 * @property SkripsiModel $skripsi
 * @property StudentModel $student
 * @property SignatureModel $signature
 * @property StatusHistoryModel $statusHistory
 * @property DepartmentModel $department
 * @property UserModel $user
 * @property Exporter $exporter
 * @property Mailer $mailer
 * @property Uploader $uploader
 */
class Skripsi extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('SkripsiModel', 'skripsi');
        $this->load->model('LogbookModel', 'logbook');
        $this->load->model('StudentModel', 'student');
        $this->load->model('StatusHistoryModel', 'statusHistory');
		$this->load->model('SignatureModel', 'signature');

        $this->load->model('DepartmentModel', 'department');
        $this->load->model('UserModel', 'user');
        $this->load->model('modules/Mailer', 'mailer');
        $this->load->model('modules/Exporter', 'exporter');
        $this->load->model('modules/Uploader', 'uploader');

        $this->setFilterMethods([
			'validate_skripsi' => 'POST|PUT',
			'print_logbook' => 'POST|GET',
		]);
    }

    /**
     * Show Skripsi index page.
     */
    public function index()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_VIEW);

        $filters = array_merge($_GET, ['page' => get_url_param('page', 1)]);

        $export = $this->input->get('export');
        if ($export) unset($filters['page']);

        $civitasLoggedIn = UserModel::loginData('id_civitas', '-1');
        $civitasType = UserModel::loginData('civitas_type', 'Admin');
		if($civitasType == "DOSEN"){
            $filters['dosen'] = $civitasLoggedIn;
        }else if($civitasType == "MAHASISWA"){
            $filters['mahasiswa'] = $civitasLoggedIn;
        }
        $skripsis = $this->skripsi->getAll($filters);

        if ($export) {
            $this->exporter->exportFromArray('skripsi', $skripsis);
        }

        $this->render('skripsi/index', compact('skripsis'));
    }

    /**
     * Show Skripsi data.
     *
     * @param $id
     */
    public function view($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_VIEW);

        $skripsi = $this->skripsi->getById($id);
        $logbooks = $this->logbook->getBySkripsiId($id);

        $this->render('skripsi/view', compact('skripsi', 'logbooks'));
    }

    /**
     * Show create Skripsi.
     */
    public function create()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_CREATE);
        $civitasLogin = UserModel::loginData();
        $pembimbingId = '';
        $pembimbing = '';
        if($civitasLogin['civitas_type'] == 'MAHASISWA'){
            $student = $this->student->getById($civitasLogin['id_civitas']);
            $pembimbingId = $student['id_pembimbing'];
            $pembimbing = $student['nama_pembimbing'];
        }
        $students = $this->student->getAll(['status'=> StudentModel::STATUS_ACTIVE]);

        $this->render('skripsi/create', compact('students', 'pembimbingId', 'pembimbing'));
    }

    /**
     * Save new Skripsi data.
     */
    public function save()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_CREATE);

        if ($this->validate()) {
            $studentId = $this->input->post('student');
            $judul = $this->input->post('judul');
            $pembimbingId = $this->input->post('id_pembimbing');
            
            $skripsi = $this->skripsi->getBy([
                'skripsis.id_student' => $studentId,
                'skripsis.status !=' => SkripsiModel::STATUS_REJECTED,
            ]);
            if(!empty($skripsi)){
                flash('warning','You cant create skripsi before your skripsi validate','skripsi/create');
            }

            $student = $this->student->getById($studentId);
            $save = $this->skripsi->create([
                'id_student' => $studentId,
                'id_lecturer' => if_empty($pembimbingId, null),
                'judul' => $judul,
            ]);

            if ($save) {
                flash('success', "Skripsi {$student['name']} successfully created", 'skripsi');
            } else {
                flash('danger', "Create Skripsi failed, try again of contact administrator");
            }
        }
        $this->create();
    }

    /**
     * Show edit Skripsi form.
     * @param $id
     */
    public function edit($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_EDIT);

        $skripsi = $this->skripsi->getById($id);
        $students = $this->student->getAll(['status'=> StudentModel::STATUS_ACTIVE]);

        $this->render('skripsi/edit', compact('students', 'skripsi'));
    }

    /**
     * Save new Skripsi data.
     * @param $id
     */
    public function update($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_EDIT);

        if ($this->validate($this->_validation_rules($id))) {
            $studentId = $this->input->post('student');
            $judul = $this->input->post('judul');
            $pembimbingId = $this->input->post('id_pembimbing');

            $skripsi = $this->skripsi->getById($id);

            $save = $this->skripsi->update([
                'id_student' => $studentId,
                'id_lecturer' => if_empty($pembimbingId, null),
                'judul' => $judul,
            ], $id);

            if ($save) {
                flash('success', "Skripsi {$skripsi['nama_mahasiswa']} successfully updated", 'skripsi');
            } else {
                flash('danger', "Update Skripsi failed, try again of contact administrator");
            }
        }
        $this->edit($id);
    }

    /**
     * Perform deleting Skripsi data.
     *
     * @param $id
     */
    public function delete($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_SKRIPSI_DELETE);

        $skripsi = $this->skripsi->getById($id);
        // push any status absent to history
        $this->statusHistory->create([
            'type' => StatusHistoryModel::TYPE_SKRIPSI,
            'id_reference' => $id,
            'status' => $skripsi['status'],
            'description' => "Skripsi is deleted",
            'data' => json_encode($skripsi)
        ]);

        if ($this->skripsi->delete($id, true)) {
            flash('warning', "Skripsi {$skripsi['nama_mahasiswa']} successfully deleted");
        } else {
            flash('danger', "Delete Skripsi failed, try again or contact administrator");
        }
        redirect('skripsi');
    }

    /**
     * Validate absent data.
     *
     * @param null $id
     */
    public function validate_skripsi($id = null)
    {
		if ($this->validate(['status' => 'trim|required'])) {
			$id = if_empty($this->input->post('id'), $id);
			$status = $this->input->post('status');
			$description = $this->input->post('description');

			$this->db->trans_start();

            $skripsi = $this->skripsi->getById($id);

            // push any status absent to history
            $this->statusHistory->create([
                'type' => StatusHistoryModel::TYPE_SKRIPSI,
                'id_reference' => $id,
                'status' => $status=="VALIDATED" ? SkripsiModel::STATUS_ACTIVE : SkripsiModel::STATUS_REJECTED,
                'description' => $description,
                'data' => json_encode($skripsi)
            ]);

            $this->skripsi->update([
                'status' => $status=="VALIDATED" ? SkripsiModel::STATUS_ACTIVE : SkripsiModel::STATUS_REJECTED
            ], $id);

            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                $statusClass = 'warning';
                if ($status != SkripsiModel::STATUS_REJECTED) {
                    $statusClass = 'success';
                }

                $message = "Skripsi <strong>{$skripsi['nama_mahasiswa']}</strong> successfully <strong>{$status}</strong>";

                flash($statusClass, $message);
            } else {
                flash('danger', "Validating skripsi <strong>{$skripsi['requisite']}</strong> failed, try again or contact administrator");
            }
		}
		redirect(get_url_param('redirect', 'skripsi'));
    }

    /**
     * print logbook skripsi data.
     *
     * @param null $id
     */
    public function print_logbook($id){
        $dateNow = Carbon::now()->locale('id');
        $tanggalSekarang = $dateNow->isoFormat('D MMMM YYYY');
        $skripsi = $this->skripsi->getById($id);
        $logbooks = $this->logbook->getBySkripsiId($id);
        $code = $this->signature->generateCode();
        $barcodePembimbing = base_url().'guest/signature?code='.$code;
        $this->signature->create([
            'code' => $code,
            'id_reference' => $id,
            'id_lecturer' => $skripsi['id_lecturer'],
            'type' => SignatureModel::TYPE_LOGBOOK_SKRIPSI,
        ]);
                    
        $barcode = new DNS2D();
        $barcode->setStorPath(APPPATH . "cache/");
        $qrCodePembimbing = $barcode->getBarcodePNG($barcodePembimbing, "QRCODE", 2, 2);
        $options = [
            'buffer' => false,
            'view' => 'logbook/print',
            'data' => compact('skripsi', 'logbooks', 'tanggalSekarang', 'qrCodePembimbing'),
        ];
        // $this->load->view($options['view'], $options['data']);
        $output = $this->exporter->exportToPdf("Bukti bimbingan ".$skripsi['nama_mahasiswa'].".pdf", null, $options);
    }

    /**
     * Return general validation rules.
     *
     * @param array $params
     * @return array
     */
    protected function _validation_rules(...$params)
    {
        $id = isset($params[0]) ? $params[0] : 0;
        return [
            'student' => 'trim|required',
            'judul' => 'trim|required',
        ];
    }

}
