<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace yunwuxin\mail\transport;

use Swift_Mime_Message;
use yunwuxin\mail\exception\SendcloudException;
use yunwuxin\mail\Transport;

class Sendcloud extends Transport
{
    protected $user;
    protected $key;

    protected $api = "http://api.sendcloud.net/apiv2/mail/send";

    protected $query = [];

    public function __construct($config)
    {
        if (!isset($config['key'], $config['user'])) {
            throw new \InvalidArgumentException('the config "key" and "user" must be apply!');
        }

        $this->user = $config['user'];
        $this->key  = $config['key'];
    }

    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param Swift_Mime_Message $message
     * @param string[]           $failedRecipients An array of failures by-reference
     * @return int
     * @throws SendcloudException
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $data = [
            'apiUser'  => $this->user,
            'apiKey'   => $this->key,
            'from'     => $this->getAddress($message->getFrom()),
            'fromName' => $this->getFromName($message->getFrom()),
            'to'       => $this->getAddresses($message->getTo()),
            'subject'  => $message->getSubject(),
            'cc'       => $this->getAddresses($message->getCc()),
            'bcc'      => $this->getAddresses($message->getBcc()),
            'replyTo'  => $this->getAddress($message->getReplyTo()),
            'html'     => $message->getBody() ?: ''
        ];

        $this->addQuery($data);

        if (!empty($message->getChildren())) {
            foreach ($message->getChildren() as $file) {
                if ($file instanceof \Swift_Mime_Attachment) {
                    $this->addQuery('attachments[]', $file->getBody(), $file->getFilename());
                }
            }
        }

        $this->query = array_filter($this->query);
        $response    = $this->getHttpClient()->post($this->api, [
            'multipart' => $this->query,
        ]);

        $this->query = [];

        $content = $response->getBody()->getContents();

        $content = json_decode($content, true);

        if ($content === false || !$content['result']) {
            throw new SendcloudException(!empty($content['message']) ? $content['message'] : '发送失败');
        }

        return $this->numberOfRecipients($message);
    }

    protected function getFromName($data)
    {
        if (!$data) {
            return null;
        }
        $data = array_values($data);
        return reset($data);
    }

    protected function getAddress($data)
    {
        if (!$data) {
            return null;
        }
        $data = array_keys($data);
        return reset($data);
    }

    protected function getAddresses($data)
    {
        if (!$data || !is_array($data)) {
            return null;
        }
        $data = array_keys($data);
        if (empty($data)) {
            return null;
        }
        return implode(';', $data);
    }

    public function addQuery($name, $contents = null, $filename = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->addQuery($key, $value);
            }
        } else {
            $query = [
                'name'     => $name,
                'contents' => $contents,
            ];
            if ($filename) {
                $query['filename'] = $filename;
            }
            $this->query[] = $query;
        }
    }
}