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

use Swift_Attachment;
use Swift_Image;
use Swift_Mime_Attachment;

/**
 * Class Message
 * @package yunwuxin\mail
 *
 * @method setBody($body, $contentType = null, $charset = null)
 */
class Message
{
    /** @var \Swift_Message */
    protected $swift;

    public function __construct(\Swift_Message $swift)
    {
        $this->swift = $swift;
    }

    /**
     * Add a "from" address to the message.
     *
     * @param  string|array $address
     * @param  string|null  $name
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
     * @param  string|array $address
     * @param  string|null  $name
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
     * @param  string $address
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
     * @param  string|array $address
     * @param  string|null  $name
     * @param  bool         $override
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
     * @param  string|array $address
     * @param  string|null  $name
     * @return $this
     */
    public function cc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * Add a blind carbon copy to the message.
     *
     * @param  string|array $address
     * @param  string|null  $name
     * @return $this
     */
    public function bcc($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * Add a reply to address to the message.
     *
     * @param  string|array $address
     * @param  string|null  $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }

    /**
     * Add a recipient to the message.
     *
     * @param  string|array $address
     * @param  string       $name
     * @param  string       $type
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
     * @param  string $subject
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
     * @param  int $level
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
     * @param  string $file
     * @param  array  $options
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
     * @param  string $file
     * @return Swift_Mime_Attachment
     */
    protected function createAttachmentFromPath($file)
    {
        return Swift_Attachment::fromPath($file);
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * @param  string $data
     * @param  string $name
     * @param  array  $options
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
     * @param  string $data
     * @param  string $name
     * @return Swift_Mime_Attachment
     */
    protected function createAttachmentFromData($data, $name)
    {
        return Swift_Attachment::newInstance($data, $name);
    }

    /**
     * Embed a file in the message and get the CID.
     *
     * @param  string $file
     * @return string
     */
    public function embed($file)
    {
        return $this->swift->embed(Swift_Image::fromPath($file));
    }

    /**
     * Embed in-memory data in the message and get the CID.
     *
     * @param  string      $data
     * @param  string      $name
     * @param  string|null $contentType
     * @return string
     */
    public function embedData($data, $name, $contentType = null)
    {
        $image = Swift_Image::newInstance($data, $name, $contentType);

        return $this->swift->embed($image);
    }

    /**
     * Prepare and attach the given attachment.
     *
     * @param  Swift_Mime_Attachment $attachment
     * @param  array                 $options
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
     * @return \Swift_Message
     */
    public function getSwiftMessage()
    {
        return $this->swift;
    }

    /**
     * Dynamically pass missing methods to the Swift instance.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = [$this->swift, $method];

        return call_user_func_array($callable, $parameters);
    }
}