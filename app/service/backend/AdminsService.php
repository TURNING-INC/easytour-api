<?php

namespace app\service\backend;

use app\BaseController;
use app\model\Admins;
use Firebase\JWT\JWT;
use think\App;

class AdminsService extends BaseController
{
    private $admins;

    public function __construct(
        App $app,
        Admins $admins
    )
    {
        parent::__construct($app);
        $this->admins = $admins;
    }
    public function login($account, $password)
    {
        $admin = $this->admins->getByAccount($account);

        if (!$admin || strtoupper($admin['pwd']) != strtoupper(md5($password))) {
            HttpEx('账户/密码不正确，请重试');
        }

        //登陆
        $token = JWT::encode([
            'admin_id' => $admin['id'],
            'account' => $account,
            'ext' => strtotime('+7 day'),
        ], env('backend.jwt_key'), 'HS512');

        $admin->save(['token' => $token]);


        return [
            'account' => $admin['account'],
            'token' => $token,
        ];
    }
}