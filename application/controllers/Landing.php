<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property ResearchModel $research
 * @property MenuModel $menu
 * @property PageModel $page
 * @property BannerModel $banner
 * @property BlogModel $blog
 * @property AgendaModel $agenda
 * Class Dashboard
 */
class Landing extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Dashboard constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('MenuModel', 'menu');
		$this->load->model('PageModel', 'page');
		$this->load->model('BannerModel', 'banner');
		$this->load->model('BlogModel', 'blog');
		$this->load->model('AgendaModel', 'agenda');

        $this->load->library('pagination');

		$this->setFilterMethods([
			'page' => 'GET',
			'blog' => 'GET',
			'blog_view' => 'GET',
			'agenda' => 'GET',
			'search' => 'GET',
		]);
	}

	/**
	 * Show dashboard page.
	 */
	public function index()
	{
		$banners = $this->banner->getAll(['sort_by' => 'id']);
        $agendas = $this->agenda->getAll(['sort_by' => 'date', 'limit' => 7]);
		$newPosts = $this->blog->getAll([
			'limit' => 5
		  ]);

		$this->render('landing/index', compact('banners', 'agendas', 'newPosts'));
	}

	public function page($id)
	{
		$content = $this->page->getById($id);
		$this->render('landing/page', compact('content'));
	}

	public function blog($category= 'Opini')
	{
		// $filters = array_merge($_GET, ['page' => get_url_param('page', 1)]);
        // $blog = $this->blog->getAll($filters);

		
        $blog = $this->blog->getAll([
			'ref_categories.category' => 'Opini'
		]);
		
		//konfigurasi pagination
        $config['base_url'] = site_url('landing/blog'); //site url
        $config['total_rows'] = count($blog); //total row
        $config['per_page'] = 5 ;  //show record per halaman
        $config["uri_segment"] = 3;  // uri parameter
        $choice = $config["total_rows"] / $config["per_page"];
        $config["num_links"] = floor($choice);
 
        // Membuat Style pagination untuk BootStrap v4
      	$config['first_link']       = 'First';
        $config['last_link']        = 'Last';
        $config['next_link']        = 'Next';
        $config['prev_link']        = 'Prev';
        $config['full_tag_open']    = '<div class="pagging text-center"><nav><ul class="pagination justify-content-center">';
        $config['full_tag_close']   = '</ul></nav></div>';
        $config['num_tag_open']     = '<li class="page-item"><span class="page-link">';
        $config['num_tag_close']    = '</span></li>';
        $config['cur_tag_open']     = '<li class="page-item active"><span class="page-link">';
        $config['cur_tag_close']    = '<span class="sr-only">(current)</span></span></li>';
        $config['next_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['next_tagl_close']  = '<span aria-hidden="true">&raquo;</span></span></li>';
        $config['prev_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['prev_tagl_close']  = '</span>Next</li>';
        $config['first_tag_open']   = '<li class="page-item"><span class="page-link">';
        $config['first_tagl_close'] = '</span></li>';
        $config['last_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['last_tagl_close']  = '</span></li>';
 
        $this->pagination->initialize($config);
        $data['page'] = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
 
		$filters['limit'] = $config["per_page"];
		$filters['start'] = $data['page'];
		$filters['category'] = $category;
		$data['category'] = $category;
        //panggil function get_mahasiswa_list yang ada pada mmodel mahasiswa_model. 
        $data['data'] = $this->blog->get_blog_list($filters);   
 
        $data['pagination'] = $this->pagination->create_links();
		
		$this->render('landing/blog', $data);
	}
	public function blog_view($id)
	{
        $blog = $this->blog->getById($id);
		$this->blog->updating([
			'count_view' => ++$blog['count_view'],
		], $id);
		$this->render('landing/blog_view', compact('blog'));
	}

	public function agenda($id)
	{
        $agenda = $this->agenda->getById($id);
		$this->render('landing/agenda', compact('agenda'));
	}

	public function search()
	{
		$filters = ['search' => get_url_param('cari', 1)];
		$searches = $this->blog->getAll($filters);
		//konfigurasi pagination
        $config['base_url'] = site_url('landing/review_curriculum'); //site url
        $config['total_rows'] = count($searches); //total row
        $config['per_page'] = 5 ;  //show record per halaman
        $config["uri_segment"] = 3;  // uri parameter
        $choice = $config["total_rows"] / $config["per_page"];
        $config["num_links"] = floor($choice);
 
        // Membuat Style pagination untuk BootStrap v4
      	$config['first_link']       = 'First';
        $config['last_link']        = 'Last';
        $config['next_link']        = 'Next';
        $config['prev_link']        = 'Prev';
        $config['full_tag_open']    = '<div class="pagging text-center"><nav><ul class="pagination justify-content-center">';
        $config['full_tag_close']   = '</ul></nav></div>';
        $config['num_tag_open']     = '<li class="page-item"><span class="page-link">';
        $config['num_tag_close']    = '</span></li>';
        $config['cur_tag_open']     = '<li class="page-item active"><span class="page-link">';
        $config['cur_tag_close']    = '<span class="sr-only">(current)</span></span></li>';
        $config['next_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['next_tagl_close']  = '<span aria-hidden="true">&raquo;</span></span></li>';
        $config['prev_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['prev_tagl_close']  = '</span>Next</li>';
        $config['first_tag_open']   = '<li class="page-item"><span class="page-link">';
        $config['first_tagl_close'] = '</span></li>';
        $config['last_tag_open']    = '<li class="page-item"><span class="page-link">';
        $config['last_tagl_close']  = '</span></li>';
 
        $this->pagination->initialize($config);
        $data['page'] = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
 
		$filters['limit'] = $config["per_page"];
		$filters['start'] = $data['page'];
        //panggil function get_mahasiswa_list yang ada pada mmodel mahasiswa_model. 
        $data['data'] = $this->blog->get_blog_list($filters);           
 
        $data['pagination'] = $this->pagination->create_links();
		
		$data['search'] = get_url_param('cari', 1);
		$this->render('landing/search', $data);
	}
}
