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

use think\Collection;

/**
 * Class Mailable
 * @package yunwuxin\mail
 *
 * @property string $queue
 * @property integer $delay
 * @property string $connection
 */
class Mailable
{
    /** @var array 发信人 */
    public $from = [];

    /** @var array 收信人 */
    public $to = [];

    /** @var array 抄送 */
    public $cc = [];

    /** @var array 密送 */
    public $bcc = [];

    /** @var array 回复人 */
    public $replyTo = [];

    /** @var string 标题 */
    public $subject;

    /** @var string 邮件内容(富文本) */
    public $view;

    /** @var string 邮件内容(纯文本) */
    public $textView;

    /** @var string 邮件内容(MarkDown) */
    public $markdown;

    /** @var array 动态数据 */
    public $viewData = [];

    /** @var array 附件(文件名) */
    public $attachments = [];

    /** @var array 附件(数据) */
    public $rawAttachments = [];

    public $callbacks = [];

    public $markdownCallback = null;

    protected function build()
    {
        //...
    }

    public function withMessage($callback)
    {
        $this->callbacks[] = $callback;

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
     * @param object|array|string $address
     * @param string|null $name
     * @param string $property
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

    public function markdown($markdown, array $data = [], $callback = null)
    {
        $this->markdown         = $markdown;
        $this->viewData         = $data;
        $this->markdownCallback = $callback;

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
     * @return $this
     */
    public function attach($file)
    {
        $this->attachments[] = $file;

        return $this;
    }

    /**
     * 设置附件
     * @param       $data
     * @param       $name
     * @return $this
     */
    public function attachData($data, $name)
    {
        $this->rawAttachments[] = compact('data', 'name');

        return $this;
    }
}
