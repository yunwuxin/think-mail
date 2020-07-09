<?php
/**
 * Created by PhpStorm.
 * User: yunwuxin
 * Date: 2018/5/11
 * Time: 15:35
 */

namespace yunwuxin\facade;

use think\Facade;

/**
 * Class Mail
 *
 * @package yunwuxin\facade
 * @mixin \yunwuxin\Mail
 * @method void send(\yunwuxin\mail\Mailable $mailable) static
 * @method void sendNow(\yunwuxin\mail\Mailable $mailable) static
 * @method void queue(\yunwuxin\mail\Mailable $mailable) static
 */
class Mail extends Facade
{
    protected static function getFacadeClass()
    {
        return \yunwuxin\Mail::class;
    }
}