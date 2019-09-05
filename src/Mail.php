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

use Swift_Mailer;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use think\Manager;
use yunwuxin\mail\Mailer;

/**
 * Class Mail
 *
 * @package yunwuxin
 * @mixin Mailer
 */
class Mail extends Manager
{

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
        return $this->app->config->get("mail.{$name}");
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
        return $this->app->config->get('mail.type');
    }
}
