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

use GuzzleHttp\Client;
use Swift_Events_EventListener;
use Swift_Mime_Message;
use think\Config;

abstract class Transport implements \Swift_Transport
{
    public $plugins = [];

    /**
     * Test if this Transport mechanism has started.
     *
     * @return bool
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * Start this Transport mechanism.
     */
    public function start()
    {
        return true;
    }

    /**
     * Stop this Transport mechanism.
     */
    public function stop()
    {
        return true;
    }

    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param Swift_Mime_Message $message
     * @param string[]           $failedRecipients An array of failures by-reference
     *
     * @return int
     */
    abstract public function send(Swift_Mime_Message $message, &$failedRecipients = null);

    /**
     * Register a plugin in the Transport.
     *
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);
    }

    /**
     * Get the number of recipients.
     *
     * @param  \Swift_Mime_Message $message
     * @return int
     */
    protected function numberOfRecipients(Swift_Mime_Message $message)
    {
        return count(array_merge(
            (array) $message->getTo(), (array) $message->getCc(), (array) $message->getBcc()
        ));
    }

    protected function getHttpClient()
    {
        $config = Config::get('mail.guzzle');
        return new Client($config);
    }

}