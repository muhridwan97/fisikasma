<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Curriculum
 * @property LecturerModel $lecturer
 * @property BlogModel $blog
 * @property StudentModel $student
 * @property StatusHistoryModel $statusHistory
 * @property CategoryModel $category
 * @property DepartmentModel $department
 * @property UserModel $user
 * @property Exporter $exporter
 * @property Mailer $mailer
 * @property Uploader $uploader
 */
class Blog extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('BlogModel', 'blog');
        $this->load->model('StudentModel', 'student');
        $this->load->model('LecturerModel', 'lecturer');
        $this->load->model('StatusHistoryModel', 'statusHistory');
        $this->load->model('CategoryModel', 'category');

        $this->load->model('DepartmentModel', 'department');
        $this->load->model('UserModel', 'user');
        $this->load->model('modules/Mailer', 'mailer');
        $this->load->model('modules/Exporter', 'exporter');
        $this->load->model('modules/Uploader', 'uploader');

        $this->setFilterMethods([
		]);
    }

    /**
     * Show Curriculum index page.
     */
    public function index()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_VIEW);

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
        $blogs = $this->blog->getAll($filters);

        if ($export) {
            $this->exporter->exportFromArray('blog', $blogs);
        }

        $this->render('blog/index', compact('blogs'));
    }

    /**
     * Show Skripsi data.
     *
     * @param $id
     */
    public function view($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_VIEW);

        $blog = $this->blog->getById($id);

        $this->render('blog/view', compact('blog'));
    }

    /**
     * Show create Research.
     */
    public function create()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_CREATE);
        $civitasLogin = UserModel::loginData();
        $pembimbingId = '';
        $pembimbing = '';
        if($civitasLogin['civitas_type'] == 'MAHASISWA'){
            $student = $this->student->getById($civitasLogin['id_civitas']);
            $pembimbingId = $student['id_pembimbing'];
            $pembimbing = $student['nama_pembimbing'];
        }
        if(AuthorizationModel::hasPermission(PERMISSION_BLOG_VALIDATE)){
            $users = $this->user->getAll(['status'=> UserModel::STATUS_ACTIVATED]);
        }else{
            $users = $this->user->getAll([
                'status'=> UserModel::STATUS_ACTIVATED,
                'id' => $civitasLogin['id']
            ]);
        }
        $categories = $this->category->getAll();

        $this->render('blog/create', compact('users', 'categories'));
    }

    /**
     * Save new Research data.
     */
    public function save()
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_CREATE);

        if ($this->validate()) {
            $title = $this->input->post('title');
            $body = $this->input->post('body');
            $user = $this->input->post('user');
            $date = $this->input->post('date');
            $category = $this->input->post('category');
            $description = $this->input->post('description');

            
            $attachment = "";
            $photo = "";
            if (!empty($_FILES['attachment']['name'])) {
                $options = ['destination' => 'blog/' . date('Y/m')];
                if ($this->uploader->uploadTo('attachment', $options)) {
                    $uploadedData = $this->uploader->getUploadedData();
                    $attachment = $uploadedData['uploaded_path'];
                } else {
                    flash('danger', $this->uploader->getDisplayErrors(), '_back', 'blog/create');
                }
            }
            if (!empty($_FILES['photo']['name'])) {
                $options = ['destination' => 'blog/' . date('Y/m')];
                if ($this->uploader->uploadTo('photo', $options)) {
                    $uploadedData = $this->uploader->getUploadedData();
                    $photo = $uploadedData['uploaded_path'];
                } else {
                    flash('danger', $this->uploader->getDisplayErrors(), '_back', 'blog/create');
                }
            }
            $this->db->trans_start();
            $this->blog->create([
                'title' => $title,
                'body' => $body,
                'id_category' => $category,
                'date' => format_date($date),
                'writed_by' => $user,
                'attachment' => $attachment,
                'photo' => $photo,
                'description' => $description,
            ]);


            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                flash('success', "Blog {$title} successfully created", 'blog');
            } else {
                flash('danger', "Create Blog failed, try again of contact administrator");
            }
        }
        $this->create();
    }
    /**
     * Show edit Curriculum form.
     * @param $id
     */
    public function edit($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_EDIT);

        $blog = $this->blog->getById($id);
        $users = $this->user->getAll(['status'=> UserModel::STATUS_ACTIVATED]);
        $categories = $this->category->getAll();

        $this->render('blog/edit', compact('blog', 'users', 'categories'));
    }

    /**
     * Save new Curriculum data.
     * @param $id
     */
    public function update($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_EDIT);

        if ($this->validate($this->_validation_rules($id))) {
            $title = $this->input->post('title');
            $body = $this->input->post('body');
            $user = $this->input->post('user');
            $date = $this->input->post('date');
            $category = $this->input->post('category');
            $description = $this->input->post('description');

            $blog = $this->blog->getById($id);
            $attachment = $blog['attachment'];
            $photo = $blog['photo'];
            if (!empty($_FILES['attachment']['name'])) {
                $options = ['destination' => 'blog/' . date('Y/m')];
                if ($this->uploader->uploadTo('attachment', $options)) {
                    $uploadedData = $this->uploader->getUploadedData();
                    $attachment = $uploadedData['uploaded_path'];
                } else {
                    flash('danger', $this->uploader->getDisplayErrors(), '_back', 'blog/edit/'.$id);
                }
            }
            if (!empty($_FILES['photo']['name'])) {
                $options = ['destination' => 'blog/' . date('Y/m')];
                if ($this->uploader->uploadTo('photo', $options)) {
                    $uploadedData = $this->uploader->getUploadedData();
                    $photo = $uploadedData['uploaded_path'];
                } else {
                    flash('danger', $this->uploader->getDisplayErrors(), '_back', 'blog/edit/'.$id);
                }
            }
            $this->db->trans_start();
            
            $this->blog->update([
                'title' => $title,
                'body' => $body,
                'id_category' => $category,
                'date' => format_date($date),
                'writed_by' => $user,
                'attachment' => $attachment,
                'photo' => $photo,
                'description' => $description,
            ], $id);


            $this->db->trans_complete();

            if ($this->db->trans_status()) {
                flash('success', "Blog {$title} successfully updated", 'blog');
            } else {
                flash('danger', "Create Blog failed, try again of contact administrator");
            }
        }
        $this->edit($id);
    }

    /**
     * Perform deleting Research data.
     *
     * @param $id
     */
    public function delete($id)
    {
        AuthorizationModel::mustAuthorized(PERMISSION_BLOG_DELETE);

        $blog = $this->blog->getById($id);
        // push any status absent to history
        $this->statusHistory->create([
            'type' => StatusHistoryModel::TYPE_BLOG,
            'id_reference' => $id,
            'status' => $blog['status'],
            'description' => "Blog is deleted",
            'data' => json_encode($blog)
        ]);

        if ($this->blog->delete($id, true)) {
            flash('warning', "Blog {$blog['title']} successfully deleted");
        } else {
            flash('danger', "Delete Blog failed, try again or contact administrator");
        }
        redirect('blog');
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
            'title' => 'trim|required',
            'body' => 'trim|required',
            'date' => 'trim|required',
            'user' => 'trim|required',
        ];
    }

}
