<?php

namespace app\model;

use think\Model;

class Users extends Model
{
    const TYPE_USER = 0; //普通用户
    const TYPE_MERCHANT = 1; //商家账号
    const TYPE_STAFF = 2; //商家员工账号


    const NOMRAL = 0;
    const FORBIDDEN = 1;

    const MP_WX = 'wx'; //微信平台

}