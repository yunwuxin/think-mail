<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace yunwuxin;

use InvalidArgumentException;
use Swift_Mailer;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use think\helper\Arr;
use think\Manager;
use yunwuxin\mail\Mailable;
use yunwuxin\mail\Mailer;

/**
 * Class Mail
 *
 * @package yunwuxin
 * @method Mailer from($users)
 * @method Mailer to($users)
 * @method Mailer cc($users)
 * @method Mailer bcc($users)
 * @method send(Mailable $mailable)
 * @method sendNow(Mailable $mailable)
 * @method queue(Mailable $mailable)
 * @method array failures()
 */
class Mail extends Manager
{
    public function getConfig(string $name = null, $default = null)
    {
        if (!is_null($name)) {
            return $this->app->config->get('mail.' . $name, $default);
        }

        return $this->app->config->get('mail');
    }

    public function getTransportConfig($transport, $name = null, $default = null)
    {
        if ($config = $this->getConfig("transports.{$transport}")) {
            return Arr::get($config, $name, $default);
        }

        throw new InvalidArgumentException("Transport [$transport] not found.");
    }

    protected function createSmtpDriver($config)
    {
        $transport = new Swift_SmtpTransport($config['host'], $config['port']);
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

    protected function createSendmailDriver($config)
    {
        return new Swift_SendmailTransport($config['command']);
    }

    protected function resolveConfig(string $name)
    {
        return $this->getTransportConfig($name);
    }

    protected function createDriver(string $name)
    {
        $transport = parent::createDriver($name);
        $swift     = new Swift_Mailer($transport);

        /** @var Mailer $mailer */
        $mailer = $this->app->invokeClass(Mailer::class, [$swift]);

        $mailer->from($this->app->config->get('mail.from'));

        return $mailer;
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }
}
