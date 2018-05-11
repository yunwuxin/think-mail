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
 */
class Mail extends Facade
{
    protected static function getFacadeClass()
    {
        return \yunwuxin\Mail::class;
    }
}