<?php

namespace app\lib\Wx;

use app\lib\Tools;
use app\Request;
use think\App;
use think\facade\Log;

class Utils
{
    private $appid;
    private $secret;

    public function __construct()
    {
        $this->appid = app(Request::class)->merchant;
        $this->secret = app(Request::class)->merchant;
    }

    /**
     * 获取openid
     * @param $code
     * @return array|bool|mixed|string
     */
    public function getOpenid($code)
    {
        try {
            $res = Tools::curlRequest('https://api.weixin.qq.com/sns/jscode2session?appid=' . $this->appid . '&secret=' . $this->secret . '&js_code=' . $code . '&grant_type=authorization_code');
        } catch (\Exception $e) {
            Log::error('登录凭证校验失败：' . $e->getMessage());
            HttpEx('', 50007);
        }

        if (isset($res['errcode']) && $res['errcode']) {
            Log::warning('登录凭证校验失败：' . $res['errcode'] . ' => ' . $res['errmsg']);
            HttpEx($res['errcode'] . ' => ' . $res['errmsg'], 50007);
        }

        return $res;
    }

    /**
     * 获取用户微信手机信息
     * @param $code
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getWxPhoneInfo($code)
    {
        $accessToken = $this->getAccessToken();

        $postData = json_encode([
            'code' => $code,
        ]);

        try {
            $res = Tools::curlRequest(
                'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=' . $accessToken,
                [],
                [],
                $postData
            );
        } catch (\Exception $e) {
            Log::error('获取微信手机号失败：' . $e->getMessage());
            HttpEx('获取微信手机号失败');
        }

        if (isset($res['errcode']) && $res['errcode']) {
            Log::warning('获取微信手机号失败：' . $res['errcode'] . ' => ' . $res['errmsg']);
            HttpEx($res['errcode'] . ' => ' . $res['errmsg']);
        }

        return $res['phone_info'];
    }
}