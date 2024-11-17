<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\service\backend\UsersService;
use think\App;
use think\Request;

class UserController extends BaseController
{
    private $usersService;

    public function __construct(
        App $app,
        UsersService $usersService
    )
    {
        parent::__construct($app);
        $this->usersService = $usersService;
    }

    public function list(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $keyword = $this->request->param('keyword', '');
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 20);


        $list = $this->usersService->list($merchantId, $keyword, $page, $count);

        return ApiResponse::returnRes(['total' => $list->count(), 'list' => $list->items()]);
    }

    public function info(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $uid = $this->request->param('uid', 0);

        if (!$uid) {
            HttpEx('参数缺失');
        }

        $user = $this->usersService->getById($uid, 'merchant_id, username, avatar_url, phone, created_at');

        if (!$user || $user->merchant_id != $merchantId) {
            HttpEx('用户不存在');
        }

        return ApiResponse::returnRes($user);
    }
}