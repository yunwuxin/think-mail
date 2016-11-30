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
use yunwuxin\mail\Mailer;

/**
 * Class Mail
 * @package yunwuxin
 *
 * @method Mailer to($users)
 * @method Mailer cc($users)
 * @method Mailer bcc($users)
 * @method void send($view, array $data = [])
 * @method void sendNow($view, array $data = [])
 */
class Mail
{

    /** @var Mailer */
    protected static $mailer;

    protected static function buildSmtpTransport($config)
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

    protected static function buildSendmailTransport($config)
    {
        return Swift_SendmailTransport::newInstance($config['command']);
    }

    protected static function buildMailTransport($config)
    {
        return Swift_MailTransport::newInstance();
    }

    protected static function buildMailer()
    {
        if (!self::$mailer) {
            $config = Config::get('mail');

            $method = 'build' . Str::studly($config['transport']) . 'Transport';

            if (method_exists(self::class, $method)) {
                $transport = self::$method();
            } else {
                $className = false !== strpos($config['transport'], '\\') ? $config['transport'] : "\\yunwuxin\\mail\\transport\\" . Str::studly($config['transport']);
                if (class_exists($className)) {
                    $transport = new $className($config);
                } else {
                    throw new InvalidArgumentException("Transport [{$config['transport']}] not supported.");
                }
            }
            $swift        = Swift_Mailer::newInstance($transport);
            self::$mailer = new Mailer($swift);
        }
        return self::$mailer;
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([self::buildMailer(), $name], $arguments);
    }

}