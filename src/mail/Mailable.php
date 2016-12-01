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
use think\Config;
use think\helper\Str;
use think\View;

/**
 * Class Mailable
 * @package yunwuxin\mail
 *
 * @property string  $queue
 * @property integer $delay
 */
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

    /** @var integer 优先级 */
    protected $priority;

    public function buildMessage(Message $message)
    {
        $this->build();

        $this->buildContent($message)
            ->buildFrom($message)
            ->buildRecipients($message)
            ->buildSubject($message)
            ->buildAttachments($message)
            ->buildPriority($message);

    }

    protected function build()
    {
        //...
    }

    /**
     * 构造数据
     * @param Message $message
     * @return array
     */
    protected function buildViewData(Message $message)
    {
        $data = $this->viewData;

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $data[$property->getName()] = $property->getValue($this);
        }

        $data['message'] = $message;

        return $data;
    }

    /**
     * 添加内容
     * @param Message $message
     * @return $this
     */
    protected function buildContent(Message $message)
    {
        $data = $this->buildViewData($message);

        if (isset($this->view)) {
            $message->setBody($this->fetchView($this->view, $data), 'text/html');
        }

        if (isset($this->textView)) {
            $method = isset($this->view) ? 'addPart' : 'setBody';

            $message->$method($this->fetchView($this->textView, $data), 'text/plain');
        }
        return $this;
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

    protected function buildPriority(Message $message)
    {
        if (isset($this->priority)) {
            $message->getSwiftMessage()->setPriority($this->priority);
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
        $this->priority = $level;

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
     * 调用模板引擎渲染模板
     * @param $view
     * @param $data
     * @return string
     */
    protected function fetchView($view, $data)
    {
        return View::instance(Config::get('template'), Config::get('view_replace_str'))->fetch($view, $data);
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
}