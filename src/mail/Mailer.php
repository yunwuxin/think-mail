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
namespace yunwuxin\mail;

use Swift_Message;
use think\Config;
use think\Queue;
use think\queue\Queueable;
use think\queue\ShouldQueue;

class Mailer
{

    /** @var  \Swift_Mailer */
    protected $swift;

    /** @var array 收信人 */
    protected $to = [];

    /** @var array 抄送 */
    protected $cc = [];

    /** @var array 密送 */
    protected $bcc = [];

    /** @var array 发送失败的地址 */
    protected $failedRecipients = [];

    public function __construct(\Swift_Mailer $swift)
    {
        $this->swift = $swift;
    }

    public function to($users)
    {
        $this->to = $users;

        return $this;
    }

    public function cc($users)
    {
        $this->cc = $users;

        return $this;
    }

    public function bcc($users)
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * 发送邮件
     * @param Mailable $mailable
     */
    public function send(Mailable $mailable)
    {
        if ($mailable instanceof ShouldQueue) {
            return $this->queue($mailable);
        }

        return $this->sendNow($mailable);
    }

    /**
     * 发送邮件(立即发送)
     * @param Mailable $mailable
     */
    public function sendNow(Mailable $mailable)
    {

        $message = $this->createMessage($mailable);

        if (isset($this->to['address'])) {
            $message->to($this->to['address'], $this->to['name'], true);
        }

        if (!empty($this->cc)) {
            $message->cc($this->cc);
        }
        if (!empty($this->bcc)) {
            $message->bcc($this->bcc);
        }

        $message = $message->getSwiftMessage();

        $this->sendSwiftMessage($message);
    }

    /**
     * 推送至队列发送
     * @param Mailable $mailable
     */
    public function queue(Mailable $mailable)
    {
        $job = new SendQueuedMailable($mailable);

        if (in_array(Queueable::class, class_uses_recursive($mailable))) {
            if ($mailable->delay > 0) {
                Queue::later($mailable->delay, $job, '', $mailable->queue);
            } else {
                Queue::push($job, '', $mailable->queue);
            }
        } else {
            Queue::push($job);
        }
    }

    /**
     * 发送失败的地址
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * 创建Message
     * @param Mailable $mailable
     * @return Message
     */
    protected function createMessage(Mailable $mailable)
    {
        $message = new Message(new Swift_Message);

        $from = Config::get('mail.from');
        if (!empty($from['address'])) {
            $message->from($from['address'], $from['name']);
        }

        $mailable->buildMessage($message);

        return $message;
    }

    /**
     * 发送Message
     * @param $message
     * @return mixed
     */
    protected function sendSwiftMessage($message)
    {

        try {
            return $this->swift->send($message, $this->failedRecipients);
        } finally {
            $this->swift->getTransport()->stop();
        }
    }

}