<?php

namespace app\service\backend;

use app\BaseController;
use app\model\Admins;
use app\model\Users;
use Firebase\JWT\JWT;
use think\App;

class AdminsService extends BaseController
{
    private $admins;
    private $users;

    public function __construct(
        App $app,
        Admins $admins,
        Users $users
    )
    {
        parent::__construct($app);
        $this->admins = $admins;
        $this->users = $users;
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

        $officialUser = NULL;

        if ($admin['merchant_id'] &&
            $officialUser = $this->users->where([['merchant_id', '=', $admin['merchant_id']], ['type', '=', Users::TYPE_MERCHANT]])->find()
        ) {
            $officialUser = [
                'id' => $officialUser['id'],
                'avatar_url' => $officialUser['avatar_url'],
                'username' => $officialUser['username']
            ];
        }

        return [
            'account' => $admin['account'],
            'token' => $token,
            'official_user' => $officialUser
        ];
    }
}