<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Logbook
 * @property LogbookModel $logbook
 * @property LecturerModel $lecturer
 * @property SkripsiModel $skripsi
 * @property StatusHistoryModel $statusHistory
 * @property NotificationModel $notification
 * @property UserModel $user
 * @property Exporter $exporter
 * @property Mailer $mailer
 * @property Uploader $uploader
 * @property Uploader $uploader
 */
class Logbook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('LogbookModel', 'logbook');
        $this->load->model('LecturerModel', 'lecturer');
        $this->load->model('SkripsiModel', 'skripsi');
        $this->load->model('StatusHistoryModel', 'statusHistory');
        $this->load->model('NotificationModel', 'notification');
        $this->load->model('UserModel', 'user');
        $this->load->model('modules/Mailer', 'mailer');
        $this->load->model('modules/Exporter', 'exporter');
        $this->load->model('modules/Uploader', 'uploader');
        
        $this->setFilterMethods([
			'validate_logbook' => 'POST|PUT',
			'outstanding' => 'GET',
		]);
    }

    /**
     * Show Logbook index page.
     */
    public function index()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_VIEW);

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
        $logbooks = $this->logbook->getAll($filters);

        if ($export) {
            $this->exporter->exportFromArray('logbook', $logbooks);
        }

        $this->render('logbook/index', compact('logbooks'));
    }

    /**
     * Show Logbook data.
     *
     * @param $id
     */
    public function view($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_VIEW);

        $logbook = $this->logbook->getById($id);

        $this->render('logbook/view', compact('logbook'));
    }

    /**
     * Show Outstanding Logbook data.
     *
     * @param $id
     */
    public function outstanding()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_VIEW);

        $filters = array_merge($_GET, ['page' => get_url_param('page', 1)]);
        $filters['status'] = LogbookModel::STATUS_PENDING;

        $export = $this->input->get('export');
        if ($export) unset($filters['page']);

        $civitasLoggedIn = UserModel::loginData('id_civitas', '-1');
        $civitasType = UserModel::loginData('civitas_type', 'Admin');
		if($civitasType == "DOSEN"){
            $filters['dosen'] = $civitasLoggedIn;
        }else if($civitasType == "MAHASISWA"){
            $filters['mahasiswa'] = $civitasLoggedIn;
        }
        $logbooks = $this->logbook->getAll($filters);

        if ($export) {
            $this->exporter->exportFromArray('logbook', $logbooks);
        }

        $this->render('logbook/index', compact('logbooks'));
    }

    /**
     * Show create Logbook.
     */
    public function create()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_CREATE);

        $civitasLoggedIn = UserModel::loginData('id_civitas', '-1');
        $civitasType = UserModel::loginData('civitas_type', 'Admin');
        $filters = ['status' => SkripsiModel::STATUS_ACTIVE];
		if($civitasType == "DOSEN"){
            $filters['dosen'] = $civitasLoggedIn;
        }else if($civitasType == "MAHASISWA"){
            $filters['mahasiswa'] = $civitasLoggedIn;
        }
        $skripsis = $this->skripsi->getAll($filters);

        $this->render('logbook/create', compact('skripsis'));
    }

    /**
     * Save new Logbook data.
     */
    public function save()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_CREATE);

        if ($this->validate()) {
            $skripsi = $this->input->post('skripsi');
            $tanggal = $this->input->post('tanggal');
            $konsultasi = $this->input->post('konsultasi');
            $description = $this->input->post('description');

			$this->db->trans_start();
            $this->logbook->create([
                'id_skripsi' => $skripsi,
                'tanggal' => format_date($tanggal),
                'konsultasi' => $konsultasi,
                'description' => $description,
            ]);
			$logbookId = $this->db->insert_id();

			$this->db->trans_complete();
            if ($this->db->trans_status()) {
		        $this->load->model('notifications/CreateLogbookNotification');
                $this->load->model('notifications/ValidateLogbookNotification');
                $logbook = $this->logbook->getById($logbookId);
                $lecturer = $this->lecturer->getById($logbook['id_lecturer']);
                $this->notification
                    ->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
                    ->to(UserModel::loginData())
                    ->send(new CreateLogbookNotification(
                        $logbook
                    ));
                $this->notification
                    ->via([Notify::DATABASE_PUSH, Notify::WEB_PUSH, Notify::MAIL_PUSH])
                    ->to($this->user->getById($lecturer['id_user']))
                    ->send(new ValidateLogbookNotification(
                        $logbook
                    ));
                flash('success', "Logbook {$konsultasi} successfully created", 'skripsi/logbook');
            } else {
                flash('danger', "Create Logbook failed, try again of contact administrator");
            }
        }
        $this->create();
    }

    /**
     * Show edit Logbook form.
     * @param $id
     */
    public function edit($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_EDIT);

        $logbook = $this->logbook->getById($id);
        $civitasLoggedIn = UserModel::loginData('id_civitas', '-1');
        $civitasType = UserModel::loginData('civitas_type', 'Admin');
        $filters = ['status' => SkripsiModel::STATUS_ACTIVE];
		if($civitasType == "DOSEN"){
            $filters['dosen'] = $civitasLoggedIn;
        }else if($civitasType == "MAHASISWA"){
            $filters['mahasiswa'] = $civitasLoggedIn;
        }
        $skripsis = $this->skripsi->getAll($filters);

        $this->render('logbook/edit', compact('skripsis', 'logbook'));
    }

    /**
     * Save new Logbook data.
     * @param $id
     */
    public function update($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_EDIT);

        if ($this->validate($this->_validation_rules($id))) {
            $skripsi = $this->input->post('skripsi');
            $tanggal = $this->input->post('tanggal');
            $konsultasi = $this->input->post('konsultasi');
            $description = $this->input->post('description');

            $logbook = $this->logbook->getById($id);

            $save = $this->logbook->update([
                'id_skripsi' => $skripsi,
                'tanggal' => format_date($tanggal),
                'konsultasi' => $konsultasi,
                'description' => $description,
            ], $id);

            if ($save) {
                flash('success', "User {$konsultasi} successfully updated", 'skripsi/logbook');
            } else {
                flash('danger', "Update Logbook failed, try again of contact administrator");
            }
        }
        $this->edit($id);
    }

    /**
     * Perform deleting Logbook data.
     *
     * @param $id
     */
    public function delete($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_LOGBOOK_DELETE);

        $logbook = $this->logbook->getById($id);

        if ($this->logbook->delete($id, true)) {
            flash('warning', "Logbook {$logbook['konsultasi']} successfully deleted");
        } else {
            flash('danger', "Delete Logbook failed, try again or contact administrator");
        }
        redirect('skripsi/logbook');
    }

    /**
     * Validate logbook data.
     *
     * @param null $id
     */
    public function validate_logbook($id = null)
    {
		if ($this->validate(['status' => 'trim|required'])) {
			$id = if_empty($this->input->post('id'), $id);
			$status = $this->input->post('status');
			$description = $this->input->post('description');

			$this->db->trans_start();

            $logbook = $this->logbook->getById($id);

            // push any status absent to history
            $this->statusHistory->create([
                'type' => StatusHistoryModel::TYPE_LOGBOOK,
                'id_reference' => $id,
                'status' => $status,
                'description' => $description,
                'data' => json_encode($logbook)
            ]);

            $this->logbook->update([
                'status' => $status
            ], $id);

            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                $statusClass = 'warning';
                if ($status != LogbookModel::STATUS_REJECTED) {
                    $statusClass = 'success';
                }

                $message = "Logbook <strong>{$logbook['nama_mahasiswa']}</strong> successfully <strong>{$status}</strong>";

                flash($statusClass, $message);
            } else {
                flash('danger', "Validating logbook <strong>{$logbook['requisite']}</strong> failed, try again or contact administrator");
            }
		}
		redirect(get_url_param('redirect', 'skripsi/logbook'));
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
            'skripsi' => 'trim|required|max_length[50]',
            'tanggal' => 'trim|required|max_length[50]',
            'konsultasi' => 'trim|required|max_length[50]',
            'description' => 'trim|required|max_length[500]',
        ];
    }

}
