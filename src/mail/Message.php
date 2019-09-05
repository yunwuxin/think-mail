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
use ReflectionClass;
use ReflectionProperty;
use Swift_Attachment;
use Swift_Image;
use Swift_Message;
use Swift_Mime_Attachment;
use think\facade\App;
use think\helper\Str;
use think\View;
use think\view\driver\Twig;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\TwigFilter;
use yunwuxin\mail\twig\TokenParser\Component;

/**
 * Class Message
 * @package yunwuxin\mail
 *
 * @method setBody($body, $contentType = null, $charset = null)
 */
class Message
{
    /** @var Swift_Message */
    protected $swift;

    /** @var View */
    protected $view;

    /** @var App */
    protected $app;

    public function __construct(Mailable $mailable, View $view, App $app)
    {
        $this->swift = new Swift_Message();
        $this->view  = $view;
        $this->app   = $app;

        $this->build($mailable);
    }

    protected function build(Mailable $mailable)
    {
        $this->app->invoke([$mailable, 'build']);

        $this->buildContent($mailable)
            ->buildFrom($mailable)
            ->buildRecipients($mailable)
            ->buildSubject($mailable)
            ->runCallbacks($mailable)
            ->buildAttachments($mailable);
    }

    /**
     * 构造数据
     * @param Mailable $mailable
     * @return array
     */
    protected function buildViewData(Mailable $mailable)
    {
        $data = $mailable->viewData;

        foreach ((new ReflectionClass($mailable))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== Mailable::class) {
                $data[$property->getName()] = $property->getValue($mailable);
            }
        }

        $data['message'] = $this;

        return $data;
    }

    /**
     * 添加内容
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildContent(Mailable $mailable)
    {
        $data = $this->buildViewData($mailable);

        if (isset($mailable->markdown)) {

            $html = $this->parseDown($mailable->markdown, $data, $mailable->markdownCallback);

            $html = (new CssToInlineStyles())->convert($html, file_get_contents(__DIR__ . '/resource/css/default.css'));

            $this->setBody($html, 'text/html');
        } else {
            if (isset($mailable->view)) {
                $this->setBody($this->fetchView($mailable->view, $data), 'text/html');
            } elseif (isset($mailable->textView)) {
                $method = isset($mailable->view) ? 'addPart' : 'setBody';

                $this->$method($this->fetchView($mailable->textView, $data), 'text/plain');
            }
        }
        return $this;
    }

    /**
     * 解析markdown
     * @param         $view
     * @param         $data
     * @param Closure $callback
     * @return string
     */
    protected function parseDown($view, $data, Closure $callback = null)
    {
        $config = $this->app->config->get('template', []);

        $twig = new Twig($this->app, $config);

        $twig->getTwig()->addFilter(new TwigFilter('markdown', function ($content) {
            $parser        = new Markdown();
            $parser->html5 = true;
            return $parser->parse($content);
        }));

        $twig->getTwig()->addTokenParser(new Component());

        $twig->getLoader()->addPath(__DIR__ . '/resource/view', 'mail');

        if ($callback) {
            $callback($twig);
        }

        return $twig->getTwig()->render($view . '.twig', $data);
    }

    /**
     * 调用模板引擎渲染模板
     * @param $view
     * @param $data
     * @return string
     */
    protected function fetchView($view, $data)
    {
        return $this->view->fetch($view, $data);
    }

    /**
     * 构造发信人
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildFrom(Mailable $mailable)
    {
        if (!empty($mailable->from)) {
            $this->from($mailable->from[0]['address'], $mailable->from[0]['name']);
        }
        return $this;
    }

    /**
     * 构造收信人
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildRecipients(Mailable $mailable)
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($mailable->{$type} as $recipient) {
                $this->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * 构造标题
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildSubject(Mailable $mailable)
    {
        if ($mailable->subject) {
            $this->subject($mailable->subject);
        } else {
            $this->subject(Str::title(Str::snake(class_basename($mailable), ' ')));
        }

        return $this;
    }

    /**
     * 构造附件
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildAttachments(Mailable $mailable)
    {
        foreach ($mailable->attachments as $attachment) {
            $this->attach($attachment['file'], $attachment['options']);
        }

        foreach ($mailable->rawAttachments as $attachment) {
            $this->attachData(
                $attachment['data'], $attachment['name'], $attachment['options']
            );
        }

        return $this;
    }

    /**
     * 执行回调
     *
     * @param Mailable $mailable
     * @return $this
     */
    protected function runCallbacks(Mailable $mailable)
    {
        foreach ($mailable->callbacks as $callback) {
            $callback($this->getSwiftMessage());
        }

        return $this;
    }

    /**
     * Add a "from" address to the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        $this->swift->setFrom($address, $name);

        return $this;
    }

    /**
     * Set the "sender" of the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @return $this
     */
    public function sender($address, $name = null)
    {
        $this->swift->setSender($address, $name);

        return $this;
    }

    /**
     * Set the "return path" of the message.
     *
     * @param string $address
     * @return $this
     */
    public function returnPath($address)
    {
        $this->swift->setReturnPath($address);

        return $this;
    }

    /**
     * Add a recipient to the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @param bool         $override
     * @return $this
     */
    public function to($address, $name = null, $override = false)
    {
        if ($override) {
            $this->swift->setTo($address, $name);

            return $this;
        }

        return $this->addAddresses($address, $name, 'To');
    }

    /**
     * Add a carbon copy to the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @return $this
     */
    public function cc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * Add a blind carbon copy to the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @return $this
     */
    public function bcc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * Add a reply to address to the message.
     *
     * @param string|array $address
     * @param string|null  $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }

    /**
     * Add a recipient to the message.
     *
     * @param string|array $address
     * @param string       $name
     * @param string       $type
     * @return $this
     */
    protected function addAddresses($address, $name, $type)
    {
        if (is_array($address)) {
            $this->swift->{"set{$type}"}($address, $name);
        } else {
            $this->swift->{"add{$type}"}($address, $name);
        }

        return $this;
    }

    /**
     * Set the subject of the message.
     *
     * @param string $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->swift->setSubject($subject);

        return $this;
    }

    /**
     * Set the message priority level.
     *
     * @param int $level
     * @return $this
     */
    public function priority($level)
    {
        $this->swift->setPriority($level);

        return $this;
    }

    /**
     * Attach a file to the message.
     *
     * @param string $file
     * @param array  $options
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        $attachment = $this->createAttachmentFromPath($file);

        return $this->prepAttachment($attachment, $options);
    }

    /**
     * Create a Swift Attachment instance.
     *
     * @param string $file
     * @return Swift_Mime_Attachment
     */
    protected function createAttachmentFromPath($file)
    {
        return Swift_Attachment::fromPath($file);
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * @param string $data
     * @param string $name
     * @param array  $options
     * @return $this
     */
    public function attachData($data, $name, array $options = [])
    {
        $attachment = $this->createAttachmentFromData($data, $name);

        return $this->prepAttachment($attachment, $options);
    }

    /**
     * Create a Swift Attachment instance from data.
     *
     * @param string $data
     * @param string $name
     * @return Swift_Mime_Attachment
     */
    protected function createAttachmentFromData($data, $name)
    {
        return new Swift_Attachment($data, $name);
    }

    /**
     * Embed a file in the message and get the CID.
     *
     * @param string $file
     * @return string
     */
    public function embed($file)
    {
        return $this->swift->embed(Swift_Image::fromPath($file));
    }

    /**
     * Embed in-memory data in the message and get the CID.
     *
     * @param string      $data
     * @param string      $name
     * @param string|null $contentType
     * @return string
     */
    public function embedData($data, $name, $contentType = null)
    {
        $image = new Swift_Image($data, $name, $contentType);

        return $this->swift->embed($image);
    }

    /**
     * Prepare and attach the given attachment.
     *
     * @param Swift_Mime_Attachment $attachment
     * @param array                 $options
     * @return $this
     */
    protected function prepAttachment($attachment, $options = [])
    {

        if (isset($options['mime'])) {
            $attachment->setContentType($options['mime']);
        }

        if (isset($options['as'])) {
            $attachment->setFilename($options['as']);
        }

        $this->swift->attach($attachment);

        return $this;
    }

    /**
     * Get the underlying Swift Message instance.
     *
     * @return Swift_Message
     */
    public function getSwiftMessage()
    {
        return $this->swift;
    }

    /**
     * Dynamically pass missing methods to the Swift instance.
     *
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = [$this->swift, $method];

        return call_user_func_array($callable, $parameters);
    }
}
