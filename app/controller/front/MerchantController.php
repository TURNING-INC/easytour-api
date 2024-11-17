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
        unset($merchant['id']);
        unset($merchant['encode_id']);

       return ApiResponse::returnRes($merchant);
    }
}