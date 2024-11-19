<?php

namespace app\lib\Wx;

use app\lib\Tools;
use app\model\AccessToken;
use app\model\Merchants;
use think\Request;
use think\App;
use think\facade\Log;

class Utils
{
    private $appid;
    private $secret;
    private $accessToken;

    public function __construct()
    {
        $merchantId = app(Request::class)->merchant->id ?? app(Request::class)->admin->merchant_id;
        $merchant = app(Merchants::class)->find($merchantId);

        if (!$merchant->wx_app_id || !$merchant->wx_app_secret) {
            HttpEx('未配置mch信息.');
        }

        $this->appid = $merchant->wx_app_id;
        $this->secret = $merchant->wx_app_secret;

        $this->accessToken = app(AccessToken::class);
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

    public function getAccessToken()
    {
        $accessToken = $this->accessToken->getByAppid($this->appid);

        if (!$accessToken || !$accessToken->access_token || !$accessToken->expires_time || $accessToken->expires_time < time()) {
            $accessToken = $this->getNewAccessToken();
        }

        return $accessToken['access_token'];
    }

    public function getNewAccessToken()
    {
        $accessToken = $this->accessToken->getByAppid($this->appid);

        try {
            $res = Tools::curlRequest('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->secret);

        } catch (\Exception $e) {
            Log::error('获取access_token失败：' . $e->getMessage());
            HttpEx('获取access_token失败');
        }

        if (isset($res['errcode']) && $res['errcode']) {
            Log::warning('获取access_token失败：' . $res['errcode'] . ' => ' . $res['errmsg']);
            HttpEx($res['errcode'] . ' => ' . $res['errmsg']);
        }

        $expiresTime = time() + $res['expires_in'] - 1000;

        if (!$accessToken) {
            $this->accessToken->insert([
                'appid' => $this->appid,
                'expires_time' => $expiresTime,
                'access_token' => $res['access_token'],
            ]);
        } else {
            $accessToken->save([
                'expires_time' => $expiresTime,
                'access_token' => $res['access_token'],
            ]);
        }

        return $res;
    }
}