<?php

namespace app\model;

use think\Model;

class AccessToken extends Model
{
    public function getByAppid($appid)
    {
        return AccessToken::where([
            'appid' => $appid
        ])->find();
    }
}