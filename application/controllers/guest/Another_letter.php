<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Carbon\Carbon;
/**
 * Class Course
 * @property RepositoryModel $repository
 * @property NotificationModel $notification
 * @property Exporter $exporter
 * @property Uploader $uploader
 * @property Mailer $mailer
 */
class Another_letter extends App_Controller
{
	protected $layout = 'layouts/landing';
	/**
	 * Course constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->model('RepositoryModel', 'repository');
		$this->load->model('NotificationModel', 'notification');
		$this->load->model('modules/Exporter', 'exporter');
		$this->load->model('modules/Uploader', 'uploader');
		$this->load->model('modules/Mailer', 'mailer');
		$this->load->model('notifications/CreateCourseNotification');

		$this->setFilterMethods([
		]);
	}

	/**
     * Show Repository index page.
     */
    public function index()
    {
        $filters = array_merge($_GET, ['page' => get_url_param('page', 1)]);
        $repositories = $this->repository->getAll($filters);

        $this->render('repository/guest', compact('repositories'));
    }
}
