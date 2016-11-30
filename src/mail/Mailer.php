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

use Closure;
use InvalidArgumentException;
use Swift_Message;
use think\Config;
use think\View;

class Mailer
{

    /** @var  View */
    protected $view;

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

    public function send($view, array $data = [], $callback = null)
    {
        if ($view instanceof Mailable) {
            return $view->send($this);
        }

        list($view, $plain, $raw) = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        $this->addContent($message, $view, $plain, $raw, $data);

        $this->callMessageBuilder($callback, $message);

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

    public function sendNow($view, array $data = [])
    {
        //  return $this->send();
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
     * @return Message
     */
    protected function createMessage()
    {
        $message = new Message(new Swift_Message);

        $from = Config::get('mail.from');
        if (!empty($from['address'])) {
            $message->from($from['address'], $from['name']);
        }

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

    /**
     * 添加内容
     * @param $message
     * @param $view
     * @param $plain
     * @param $raw
     * @param $data
     */
    protected function addContent(Message $message, $view, $plain, $raw, $data)
    {
        if (isset($view)) {
            $message->setBody($this->getView($view, $data), 'text/html');
        }

        if (isset($plain)) {
            $method = isset($view) ? 'addPart' : 'setBody';

            $message->$method($this->getView($plain, $data), 'text/plain');
        }

        if (isset($raw)) {
            $method = (isset($view) || isset($plain)) ? 'addPart' : 'setBody';

            $message->$method($raw, 'text/plain');
        }
    }

    /**
     * 调用模板引擎渲染模板
     * @param $view
     * @param $data
     * @return string
     */
    protected function getView($view, $data)
    {
        if (!$this->view) {
            $this->view = View::instance(Config::get('template'), Config::get('view_replace_str'));
        }

        return $this->view->fetch($view, $data);
    }

    /**
     * 解析模板
     * @param $view
     * @return array
     */
    protected function parseView($view)
    {
        if (is_string($view)) {
            return [$view, null, null];
        }

        if (is_array($view) && isset($view[0])) {
            return [$view[0], $view[1], null];
        }

        if (is_array($view)) {
            return [
                isset($view['html']) ? $view['html'] : '',
                isset($view['text']) ? $view['text'] : '',
                isset($view['raw']) ? $view['raw'] : ''
            ];
        }

        throw new InvalidArgumentException('Invalid view.');
    }

    protected function callMessageBuilder($callback, Message $message)
    {
        if ($callback instanceof Closure) {
            return call_user_func($callback, $message);
        }

        throw new InvalidArgumentException('Callback is not valid.');
    }
}