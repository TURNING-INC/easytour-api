<?php

namespace app\model;

use think\Model;

class Merchants extends Model
{
    function getByEncodeId($encodeId, $languageKey='zh_cn') {
        $field = "id, encode_id, `{$languageKey}_name` as name, logo, banner, `{$languageKey}_address` as address, 
                    phone, lat, lng, business_hours, `{$languageKey}_intro` as intro, currency";

        $merchant = Merchants::where(['encode_id' => $encodeId])->field($field)->find();

        if ($merchant) {
            $merchant['banner'] = json_decode($merchant['banner'], true) ?? [];
            $merchant['business_hours'] = json_decode($merchant['business_hours'], true) ?? [];

        }

        return $merchant;
    }
}