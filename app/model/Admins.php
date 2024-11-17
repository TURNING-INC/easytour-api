<?php

namespace app\model;

use think\Model;

class Admins extends Model
{
    const NORMAL = 0;
    const FORBIDDEN = 1;

    public function getByAccount($account)
    {
        return Admins::where([
            'account' => $account,
            'del_flag' => self::NORMAL
        ])->find();
    }

    public function getById($id) {
        return Admins::where([
            'id' => $id,
            'del_flag' => self::NORMAL
        ])->find();
    }
}