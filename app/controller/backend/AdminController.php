<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\service\backend\AdminsService;
use think\App;

class AdminController extends BaseController
{
    private $adminsService;

    public function __construct(
        App $app,
        AdminsService $adminsService
    )
    {
        parent::__construct($app);
        $this->adminsService = $adminsService;
    }

    public function login() {
        $account = $this->request->param('account');
        $password = $this->request->param('pwd');

        if (!$account) HttpEx('请输入账号');
        if (!$password) HttpEx('请输入密码');

        $res = $this->adminsService->login($account, $password);

        return ApiResponse::returnRes($res);
    }

    public function logOut() {
    }
}