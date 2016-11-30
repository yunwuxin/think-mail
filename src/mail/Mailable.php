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

use ReflectionClass;
use ReflectionProperty;
use think\Collection;
use think\helper\Str;

class Mailable
{
    /** @var array 发信人 */
    protected $from = [];

    /** @var array 收信人 */
    protected $to = [];

    /** @var array 抄送 */
    protected $cc = [];

    /** @var array 密送 */
    protected $bcc = [];

    /** @var array 回复人 */
    protected $replyTo = [];

    /** @var string 标题 */
    protected $subject;

    /** @var string 邮件内容(富文本) */
    protected $view;

    /** @var string 邮件内容(纯文本) */
    protected $textView;

    /** @var array 动态数据 */
    protected $viewData = [];

    /** @var array 附件(文件名) */
    protected $attachments = [];

    /** @var array 附件(数据) */
    protected $rawAttachments = [];

    /** @var array 消息处理回调 */
    protected $callbacks = [];

    public function send(Mailer $mailer)
    {
        $this->build();

        $mailer->send($this->buildView(), $this->buildViewData(), function (Message $message) {
            $this->buildFrom($message)
                ->buildRecipients($message)
                ->buildSubject($message)
                ->buildAttachments($message)
                ->runCallbacks($message);
        });

    }

    protected function build()
    {
        //...
    }

    /**
     * 构造邮件内容
     * @return array|string
     */
    protected function buildView()
    {
        if (isset($this->view, $this->textView)) {
            return [$this->view, $this->textView];
        } elseif (isset($this->textView)) {
            return ['text' => $this->textView];
        } else {
            return $this->view;
        }
    }

    /**
     * 构造数据
     * @return array
     */
    protected function buildViewData()
    {
        $data = $this->viewData;

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * 构造发信人
     * @param Message $message
     * @return $this
     */
    protected function buildFrom(Message $message)
    {
        if (!empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }
        return $this;
    }

    /**
     * 构造收信人
     * @param $message
     * @return $this
     */
    protected function buildRecipients(Message $message)
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * 构造标题
     * @param Message $message
     * @return $this
     */
    protected function buildSubject(Message $message)
    {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }

    /**
     * 构造附件
     * @param Message $message
     * @return $this
     */
    protected function buildAttachments(Message $message)
    {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'], $attachment['name'], $attachment['options']
            );
        }

        return $this;
    }

    /**
     * 执行回调
     * @param $message
     * @return $this
     */
    protected function runCallbacks(Message $message)
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSwiftMessage());
        }

        return $this;
    }

    /**
     * 设置优先级
     * @param int $level
     * @return $this
     */
    public function priority($level = 3)
    {
        $this->callbacks[] = function (\Swift_Message $message) use ($level) {
            $message->setPriority($level);
        };

        return $this;
    }

    /**
     * 设置发信人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function from($address, $name = null)
    {
        return $this->setAddress($address, $name, 'from');
    }

    /**
     * 设置收信人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function to($address, $name = null)
    {
        return $this->setAddress($address, $name, 'to');
    }

    /**
     * 设置抄送
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function cc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'cc');
    }

    /**
     * 设置密送
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function bcc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'bcc');
    }

    /**
     * 设置回复人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function replyTo($address, $name = null)
    {
        return $this->setAddress($address, $name, 'replyTo');
    }

    /**
     * 设置地址
     *
     * @param  object|array|string $address
     * @param  string|null         $name
     * @param  string              $property
     * @return $this
     */
    protected function setAddress($address, $name = null, $property = 'to')
    {
        if (is_object($address) && !$address instanceof Collection) {
            $address = [$address];
        }

        if ($address instanceof Collection || is_array($address)) {
            foreach ($address as $user) {
                $user = $this->parseUser($user);

                $this->{$property}($user->email, isset($user->name) ? $user->name : null);
            }
        } else {
            $this->{$property}[] = compact('address', 'name');
        }

        return $this;
    }

    /**
     * 格式化用户
     * @param $user
     * @return object
     */
    protected function parseUser($user)
    {
        if (is_array($user)) {
            return (object) $user;
        } elseif (is_string($user)) {
            return (object) ['email' => $user];
        }

        return $user;
    }

    /**
     * 设置标题
     * @param $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * 设置模板
     * @param       $view
     * @param array $data
     * @return $this
     */
    public function view($view, array $data = [])
    {
        $this->view     = $view;
        $this->viewData = $data;

        return $this;
    }

    /**
     * 设置文本
     * @param       $textView
     * @param array $data
     * @return $this
     */
    public function text($textView, array $data = [])
    {
        $this->textView = $textView;
        $this->viewData = $data;

        return $this;
    }

    /**
     * 设置数据
     * @param      $key
     * @param null $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * 设置附件
     * @param       $file
     * @param array $options
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        $this->attachments[] = compact('file', 'options');

        return $this;
    }

    /**
     * 设置附件
     * @param       $data
     * @param       $name
     * @param array $options
     * @return $this
     */
    public function attachData($data, $name, array $options = [])
    {
        $this->rawAttachments[] = compact('data', 'name', 'options');

        return $this;
    }

    /**
     * 设置消息回调
     * @param $callback
     * @return $this
     */
    public function withSwiftMessage($callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

}