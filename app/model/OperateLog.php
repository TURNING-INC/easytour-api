<?php

namespace app\model;

use think\Model;

class OperateLog extends Model
{
    const FROM_FRONT = 0;   //前台
    const FROM_BACKEND = 1; //后台

    const TARGET_TYPE_ORDER = 0;

    public function log($uid, $from, $operate, $targetId, $targetType, $updateValue) {
        $updateValue = is_array($updateValue) ? json_encode($updateValue) : $updateValue;

        return OperateLog::save([
            'uid' => $uid,
            'from' => $from,
            'operate' => $operate,
            'target_id' => $targetId,
            'target_type' => $targetType,
            'update_value' => $updateValue
        ]);
    }
}