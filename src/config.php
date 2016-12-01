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

return [
    'transport'  => 'smtp', //smtp sendmail mail log sendcloud
    'host'       => 'mail.example.com',
    'port'       => 25,
    'encryption' => 'tls',
    'username'   => 'username',
    'password'   => 'password',
    'from'       => [
        'address' => 'example@example',
        'name'    => 'App Name'
    ]
];