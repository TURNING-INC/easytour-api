<?php

namespace app\model;

use think\Model;

class Merchants extends Model
{
    function getByEncodeId($encodeId, $languageKey='zh_cn', $includeAppSet=false) {
        $field = "id, encode_id, `{$languageKey}_name` as name, logo, banner, `{$languageKey}_address` as address, 
                    phone, lat, lng, `{$languageKey}_business_hours` as business_hours, `{$languageKey}_intro` as intro, 
                    currency, login_bg";

        if ($includeAppSet) {
            $field .= " ,wx_app_id, wx_app_secret, wx_mch_id, wx_mch_key";
        }

        return Merchants::where(['encode_id' => $encodeId])->field($field)->find();
    }
}