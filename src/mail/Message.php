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
use think\App;
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
 * @mixin \Nette\Mail\Message
 */
class Message
{
    /** @var \Nette\Mail\Message */
    protected $mail;

    /** @var View */
    protected $view;

    /** @var App */
    protected $app;

    public function __construct(Mailable $mailable, View $view, App $app)
    {
        $this->mail = new \Nette\Mail\Message();
        $this->view = $view;
        $this->app  = $app;

        $this->build($mailable);
    }

    protected function build(Mailable $mailable)
    {
        $this->app->invoke([$mailable, 'build'], [], true);

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

            $css = $this->app->config->get('mail.css', __DIR__ . '/resource/css/default.css');

            $html = (new CssToInlineStyles())->convert($html, file_get_contents($css));

            $this->setHtmlBody($html);
        } else {
            if (isset($mailable->view)) {
                $this->setHtmlBody($this->fetchView($mailable->view, $data));
            } elseif (isset($mailable->textView)) {
                $method = isset($mailable->view) ? 'addPart' : 'setBody';

                $this->$method($this->fetchView($mailable->textView, $data));
            }
        }
        return $this;
    }

    /**
     * 解析markdown
     * @param         $view
     * @param         $data
     * @param Closure|null $callback
     * @return string
     */
    protected function parseDown($view, $data, Closure $callback = null)
    {
        /** @var Twig $twig */
        $twig = $this->view->engine('twig');

        $parser        = new Markdown();
        $parser->html5 = true;

        $twig->getTwig()->addFilter(new TwigFilter('maildown', function ($content) use ($parser) {
            $content = preg_replace('/^[^\S\n]+/m', '', $content);
            return $parser->parse($content);
        }));

        $twig->getTwig()->addTokenParser(new Component());

        $twig->getLoader()->addPath(__DIR__ . '/resource/view', 'mail');

        if ($callback) {
            $callback($twig);
        }

        $content = $twig->getTwig()->render($view . '.twig', $data);

        //清理
        $this->view->forgetDriver('twig');

        return $content;
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
            $this->attach($attachment);
        }

        foreach ($mailable->rawAttachments as $attachment) {
            $this->attach($attachment['name'], $attachment['data']);
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
            $callback($this->mail);
        }

        return $this;
    }

    /**
     * Add a "from" address to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        $this->mail->setFrom($address, $name);

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
        $this->mail->setReturnPath($address);

        return $this;
    }

    /**
     * Add a recipient to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function to($address, $name = null)
    {
        $this->mail->addTo($address, $name);

        return $this;
    }

    /**
     * Add a carbon copy to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function cc($address, $name = null)
    {
        $this->mail->addCc($address, $name);
        return $this;
    }

    /**
     * Add a blind carbon copy to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function bcc($address, $name = null)
    {
        $this->mail->addBcc($address, $name);
        return $this;
    }

    /**
     * Add a reply to address to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        $this->mail->addReplyTo($address, $name);
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
        $this->mail->setSubject($subject);
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
        $this->mail->setPriority($level);
        return $this;
    }

    /**
     * Attach a file to the message.
     *
     * @param string $file
     * @return $this
     */
    public function attach($file, $content = null, $contentType = null)
    {
        $this->mail->addAttachment($file, $content, $contentType);
        return $this;
    }

    /**
     * Embed a file in the message and get the CID.
     *
     * @param string $file
     */
    public function embed($file, $content = null, $contentType = null)
    {
        return $this->mail->addEmbeddedFile($file, $content, $contentType);
    }

    /**
     * Get the underlying Swift Message instance.
     *
     * @return \Nette\Mail\Message
     */
    public function getMail()
    {
        return $this->mail;
    }

    /**
     * Dynamically pass missing methods to the Swift instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = [$this->mail, $method];

        return call_user_func_array($callable, $parameters);
    }
}
