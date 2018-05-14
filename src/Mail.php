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
namespace yunwuxin;

use InvalidArgumentException;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use think\Config;
use think\helper\Str;
use yunwuxin\mail\Mailable;
use yunwuxin\mail\Mailer;

/**
 * Class Mail
 *
 * @package yunwuxin
 * @method Mailer to($users)
 * @method Mailer cc($users)
 * @method Mailer bcc($users)
 * @method void send(Mailable $mailable)
 * @method void sendNow(Mailable $mailable)
 * @method void queue(Mailable $mailable)
 */
class Mail
{

    /** @var Mailer */
    protected $mailer;

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    protected function buildSmtpTransport($config)
    {
        $transport = Swift_SmtpTransport::newInstance($config['host'], $config['port']);
        if (isset($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        if (isset($config['username'], $config['password'])) {
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password']);
        }

        if (isset($config['stream'])) {
            $transport->setStreamOptions($config['stream']);
        }

        return $transport;
    }

    protected function buildSendmailTransport($config)
    {
        return Swift_SendmailTransport::newInstance($config['command']);
    }

    protected function buildMailTransport($config)
    {
        return Swift_MailTransport::newInstance();
    }

    protected function buildMailer()
    {
        if (!$this->mailer) {
            $config = $this->config;

            $method = 'build' . Str::studly($config['transport']) . 'Transport';

            if (method_exists(self::class, $method)) {
                $transport = self::$method($config);
            } else {
                $className = false !== strpos($config['transport'], '\\') ? $config['transport'] : "\\yunwuxin\\mail\\transport\\" . Str::studly($config['transport']);
                if (class_exists($className)) {
                    $transport = new $className($config);
                } else {
                    throw new InvalidArgumentException("Transport [{$config['transport']}] not supported.");
                }
            }
            $swift        = Swift_Mailer::newInstance($transport);
            $this->mailer = new Mailer($swift);
        }
        return $this->mailer;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->buildMailer(), $name], $arguments);
    }

    public static function __make(Config $config)
    {
        return new self($config->pull('mail'));
    }

}
