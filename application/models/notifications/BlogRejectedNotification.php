<?php

/**
 * Class BlogRejectedNotification
 */
class BlogRejectedNotification extends Notify
{
    private $blog;
	private $message;
	private $url;

    public function __construct($blog = null)
    {
        $this->blog = $blog;
		$this->message = "Blog {$blog['title']} is Rejected, check it out now";
		$this->url = site_url("blog/view/{$blog['id']}");
    }

    /**
     * Web push notification data.
     *
     * @param $notifiable
     * @return array
     */
    public function toWeb($notifiable)
    {
        return $data = [
            'channel' => NotificationModel::SUBSCRIBE_BLOG,
            'event' => NotificationModel::EVENT_BLOG_REJECTED,
            'payload' => [
                'message' => $this->message,
                'url' => $this->url,
                'time' => format_date('now', 'Y-m-d H:i:s'),
            ]
        ];
    }

    /**
     * Database push notification.
     *
     * @param $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'id_user' => $notifiable['id'],
            'id_related' => $this->blog['id'],
            'channel' => NotificationModel::SUBSCRIBE_BLOG,
            'event' => NotificationModel::EVENT_BLOG_REJECTED,
            'data' => json_encode([
                'message' => $this->message,
                'url' => $this->url,
                'time' => format_date('now', 'Y-m-d H:i:s'),
                'description' => 'Rejected blog data'
            ])
        ];
    }

    /**
	 * Mail notification.
	 *
	 * @param $notifiable
	 * @return array
	 */
	public function toMail($notifiable)
	{
        $username = UserModel::loginData('username');
		return [
			'to' => $notifiable['email'],
			'subject' => "Blog {$this->blog['title']} has been rejected by {$username}",
			'template' => 'emails/basic',
			'data' => [
				'name' => $notifiable['name'],
				'email' => $notifiable['email'],
				'content' => "
                    Blog <b>{$this->blog['title']}</b> has been rejected by <b>{$username}</b>,
                    <br><br>
                    Rincian: " . if_empty($this->blog['description'], 'no additional message')
			]
		];
	}
}
