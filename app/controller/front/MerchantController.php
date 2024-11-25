<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use think\Request;
use think\App;

class MerchantController extends BaseController
{
    public function info() {
        $merchant = $this->request->merchant;

        $merchant = [
            'name' => $merchant['name'],
            'logo' => $merchant['logo'],
            'banner' => json_decode($merchant['banner'], true) ?? [],
            'address' => $merchant['address'],
            'phone' => $merchant['phone'],
            'lat' => $merchant['lat'],
            'lng' => $merchant['lng'],
            'business_hours' => json_decode($merchant['business_hours'], true) ?? [],
            'intro' => $merchant['intro'],
            'currency' => $merchant['currency'],
            'login_bg' => $merchant['login_bg']

        ];

       return ApiResponse::returnRes($merchant);
    }
}