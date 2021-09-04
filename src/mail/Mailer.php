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

use Swift_Mailer;
use think\App;
use think\Queue;
use think\queue\Queueable;
use think\queue\ShouldQueue;

class Mailer
{

    /** @var  Swift_Mailer */
    protected $swift;

    /** @var array 发信人 */
    protected $from;

    /** @var array 收信人 */
    protected $to = [];

    /** @var array 抄送 */
    protected $cc = [];

    /** @var array 密送 */
    protected $bcc = [];

    /** @var array 发送失败的地址 */
    protected $failedRecipients = [];

    /** @var Queue */
    protected $queue;

    /** @var App */
    protected $app;

    public function __construct(Swift_Mailer $swift, Queue $queue, App $app)
    {
        $this->swift = $swift;
        $this->queue = $queue;
        $this->app   = $app;
    }

    public function from($users)
    {
        $this->from = $users;
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
            $this->queue($mailable);
        } else {
            $this->sendNow($mailable);
        }
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

        $this->sendMessage($message);
    }

    /**
     * 推送至队列发送
     * @param Mailable $mailable
     */
    public function queue(Mailable $mailable)
    {
        $job = new SendQueuedMailable($mailable);

        if (in_array(Queueable::class, class_uses_recursive($mailable))) {
            $queue = $this->queue->connection($mailable->connection);
            if ($mailable->delay > 0) {
                $queue->later($mailable->delay, $job, '', $mailable->queue);
            } else {
                $queue->push($job, '', $mailable->queue);
            }
        } else {
            $this->queue->push($job);
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
        if (!empty($this->from['address'])) {
            $mailable->from($this->from['address'], $this->from['name']);
        }

        return $this->app->invokeClass(Message::class, [$mailable]);
    }

    /**
     * 发送Message
     * @param Message $message
     * @return mixed
     */
    protected function sendMessage($message)
    {
        try {
            return $this->swift->send($message->getSwiftMessage(), $this->failedRecipients);
        } finally {
            $this->swift->getTransport()->stop();
        }
    }

}
