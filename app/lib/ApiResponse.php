<?php

namespace app\lib;

use think\Request;

class ApiResponse
{
    // 状态码及其对应多语言信息
    private static $codeMsg = [
        20001 => ['zh-cn'=>'签名失败', 'zh-hk'=>'簽名失敗', 'en'=>'Signature failed'],
        50007 => ['zh-cn'=>'登录失败', 'zh-hk'=>'登錄失敗', 'en'=>'Failed'],
        50008 => ['zh-cn'=>'请登录账号', 'zh-hk'=>'請登錄帳號', 'en'=>'Please login account'],
        50009 => ['zh-cn'=>'请重新登录账号', 'zh-hk'=>'請重新登錄帳號', 'en'=>'Please log in again'],
        50010 => ['zh-cn'=>'商家信息缺失', 'zh-hk'=>'商家信息缺失', 'en'=>'Merchant information missing'],
        50011 => ['zh-cn'=>'商家信息错误', 'zh-hk'=>'商家信息錯誤', 'en'=>'Merchant information error'],
        50012 => ['zh-cn'=>'平台信息缺失', 'zh-hk'=>'平台信息缺失', 'en'=>'Mp missing'],
        50013 => ['zh-cn'=>'参数错误', 'zh-hk'=>'參數錯誤', 'en'=>'Invalid param'],
        50014 => ['zh-cn'=>'数据不存在', 'zh-hk'=>'數據不存在', 'en'=>'Data does not exist'],
        50015 => ['zh-cn'=>'缺少凭证code', 'zh-hk'=>'缺少憑證code', 'en'=>'Certificate code missing'],
        50016 => ['zh-cn'=>'没有权限', 'zh-hk'=>'沒有權限', 'en'=>'Have no authority'],
        50017 => ['zh-cn'=>'数据不可为空', 'zh-hk'=>'數據不可為空', 'en'=>'Data cannot be empty'],
        50018 => ['zh-cn'=>'部分商品库存不足', 'zh-hk'=>'部分商品庫存不足', 'en'=>'Stocks of some goods are low'],
        50019 => ['zh-cn'=>'商品库存不足', 'zh-hk'=>'商品庫存不足', 'en'=>'Commodity shortage'],
        50020 => ['zh-cn'=>'当前时间还不能下单该商品', 'zh-hk'=>'當前時間還不能下單該商品', 'en'=>'This product cannot be placed at this time'],
    ];

    private static $successMsg = ['zh-cn'=>'操作成功', 'zh-hk'=>'操作成功', 'en'=>'Success'];
    private static $failMsg = ['zh-cn'=>'操作失败', 'zh-hk'=>'操作失敗', 'en'=>'Operation failure'];

    /**
     * 响应
     * @param false $data
     * @param int $code
     * @param string $msg
     * @param array $appendData
     * @param string $type
     * @return array|\think\response\Json|\think\response\Xml
     */
    public static function returnRes($data = false, int $code = 0, string $msg = '', array $appendData = [], string $type = 'json')
    {
        $language = app(Request::class)->language ?? 'zh-cn';

        if (!$msg) {
            if ($code === 0) {
                $msg = $data === false ? self::$failMsg[$language] : self::$successMsg[$language];
            } else {
                $msg = self::$codeMsg[$code][$language] ?? '';
            }
        }

        $res = [
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ];

        if ($appendData && is_array($appendData)) {
            $res = array_merge($res, $appendData);
        }

        switch ($type) {
            case 'json':
                return json($res);
            case 'xml':
                return xml($res);
            default:
                return $res;
        }
    }
}