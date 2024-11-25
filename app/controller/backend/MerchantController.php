<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\model\Users;
use app\service\backend\MerchantsService;
use app\service\backend\UsersService;
use think\App;
use think\Request;

class MerchantController extends BaseController
{
    private $merchantsService;
    private $usersService;

    public function __construct(
        App $app,
        MerchantsService $merchantsService,
        UsersService $usersService
    )
    {
        parent::__construct($app);
        $this->merchantsService = $merchantsService;
        $this->usersService = $usersService;
    }

    public function info(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $merchant = $this->merchantsService->getById($merchantId);
        $merchant['banner'] = json_decode($merchant['banner']) ?? [];
        $merchant['business_hours'] = json_decode($merchant['business_hours']) ?? [];

        unset($merchant['id']);
        unset($merchant['encode_id']);

        return ApiResponse::returnRes($merchant);
    }

    public function save(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $zhCnName = $this->request->param('zh_cn_name', '');
        $zhHkName = $this->request->param('zh_hk_name', '');
        $enName = $this->request->param('en_name', '');
        $logo = $this->request->param('logo', '');
        $banner = $this->request->param('banner');
        $zhCnIntro = $this->request->param('zh_cn_intro', '');
        $zhHkIntro = $this->request->param('zh_hk_intro', '');
        $enIntro = $this->request->param('en_intro', '');
        $businessHours = $this->request->param('business_hours');
        $lat = $this->request->param('lat', '');
        $lng = $this->request->param('lng', '');
        $zhCnAddress = $this->request->param('zh_cn_address', '');
        $zhHkAddress = $this->request->param('zh_hk_address', '');
        $enAddress = $this->request->param('en_address', '');
        $phone = $this->request->param('phone', '');
        $loginBg = $this->request->param('login_bg', '');

        $banner = json_decode($banner, true) ?? [];

        if (!$zhCnName || !$zhHkName || !$enName) {
            HttpEx('商家名缺失不完整');
        }

        if (!$logo) {
            HttpEx('商家logo不能空');
        }

        if (!$banner) {
            HttpEx('起码要有一个banner');
        }

        $this->merchantsService->save($merchantId, [
            'zh_cn_name' => $zhCnName,
            'zh_hk_name' => $zhHkName,
            'en_name' => $enName,
            'logo' => $logo,
            'banner' => json_encode($banner),
            'zh_cn_intro' => $zhCnIntro,
            'zh_hk_intro' => $zhHkIntro,
            'en_intro' => $enIntro,
            'business_hours' => $businessHours,
            'lat' => $lat,
            'lng' => $lng,
            'zh_cn_address' => $zhCnAddress,
            'zh_hk_address' => $zhHkAddress,
            'en_address' => $enAddress,
            'phone' => $phone,
            'login_bg' => $loginBg
        ]);

        return ApiResponse::returnRes(true);
    }

    //绑定
    public function bindStaff(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $phone = $this->request->param('phone', '');
        $userType = $this->request->param('user_type', '');

        if (!$phone) {
            HttpEx('缺少电话号码');
        }

        if (!in_array($userType, [Users::TYPE_MERCHANT, Users::TYPE_STAFF])) {
            HttpEx('用户类型错误');
        }

        $user = $this->usersService->getByPhone($merchantId, $phone);

        if (!$user) {
            HttpEx('用户不存在');
        }

        $user->type = $userType;
        $user->save();

        return ApiResponse::returnRes(true);
    }

    //解绑
    public function unbindStaff(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $uid = $this->request->param('uid', 0);

        if (!$uid) {
            HttpEx('缺少参数');
        }

        $user = $this->usersService->getById($uid);

        if (!$user || $user->merchant_id != $merchantId) {
            HttpEx('用户不存在');
        }

        if ($user->type != Users::TYPE_USER) {
            $user->type = Users::TYPE_USER;
            $user->save();
        }

        return ApiResponse::returnRes(true);
    }

    public function staffList(Request $request)
    {
        $merchantId = $request->admin->merchant_id;
        $list = $this->usersService->getStaffUsers($merchantId, 'id, username');

        return ApiResponse::returnRes($list);
    }
}