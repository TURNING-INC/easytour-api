<?php

namespace app\service\front;

use app\BaseController;
use app\lib\Tools;
use app\lib\Wx\Utils;
use app\model\Users;
use Firebase\JWT\JWT;
use think\App;

class UsersService extends BaseController
{
    private $users;
    private $wxUtils;

    public function __construct(
        App $app,
        Users $users,
        Utils $wxUtils
    )
    {
        parent::__construct($app);
        $this->users = $users;
        $this->wxUtils = $wxUtils;
    }

    public function getById($id, $field='*') {
        return $this->users->field($field)->find($id);
    }

    public function getByOpenid($openid, $mp) {
        $user = [];
        switch ($mp) {
            case Users::MP_WX:
                $user = $this->users->where([
                    'wx_openid' => $openid
                ])->find();

                break;
        }

        return $user;
    }

    public function decodeCode($code, $mp) {
        $openid = ['openid' => $code .'jhwjrhwkjrhwjerk'];

        //todo
//        switch ($mp) {
//            case Users::MP_WX:
//                $openid = $this->wxUtils->getOpenid($code);
//                break;
//        }

        return $openid;
    }

    public function login($code, $mp, $merchantId)
    {
        $res = $this->decodeCode($code, $mp);
        $user = $this->getByOpenid($code, $mp);

        if (!$user) {
            //注册
            $uid = $this->register($res['openid'], $mp, $merchantId);
            $user = $this->getById($uid);
        }

        //更新ip地址
        $user->ip = Tools::getIp();
        $user->save();

        //登陆
        $res['token'] = JWT::encode([
            'mp' => $mp,
            'uid' => $user->id,
            'openid' => $res['openid'],
            'ext' => strtotime('+90 day'),
        ], env('wxmp.jwt_key'), 'HS512');

        $user->save(['token' => $res['token']]);

        return $res;
    }

    protected function register($openid, $mp, $merchantId)
    {
        $openidKey = "{$mp}_openid";

        //创建用户
        $uid = $this->users->insertGetId([
            $openidKey => $openid,
            'merchant_id' => $merchantId //记录是哪个商家的用户
        ]);

        return $uid;
    }

    public function upd($uid, $updData)
    {

        //可更新用户
        $userUpdParam = ['username', 'avatar_url', 'phone_number', 'gender', 'real_name'];
        //更新数据
        $userUpdData = [];

        foreach ($updData as $k => $d) {
            if (in_array($k, $userUpdParam) && $d != '' && $d != 'null') $userUpdData[$k] = $d;
        }

        $res = false;

        //更新用户
        if ($userUpdData) {
            $this->users->where('id', $uid)->update($userUpdData);
            $res = true;
        }

        return $res;
    }
}