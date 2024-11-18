<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Wx\PayUtils;
use app\model\Orders;
use app\model\Users;
use app\service\front\OrdersService;
use app\service\front\UsersService;
use think\App;
use think\Http;
use think\Request;

class OrderController extends BaseController
{
    private $ordersService;
    private $usersService;

    public function __construct(
        App $app,
        OrdersService $ordersService,
        UsersService $usersService
    )
    {
        parent::__construct($app);
        $this->ordersService = $ordersService;
        $this->usersService = $usersService;
    }

    public function place(Request $request) {
        $merchantId = $this->request->merchant->id;
        $uid = $request->user->id;
        $mp = $request->mp;
        $languageKey = $this->request->languageKey;
        $skuList = $this->request->param('sku_list');
        $skuList = json_decode($skuList, true) ?? [];

        if (!$skuList) {
            HttpEx('', 50017);
        }

        foreach ($skuList as $sku) {
            if (!isset($sku['sku_id']) || !$sku['sku_id']) {
                HttpEx('', 50013);
            }

            if (!isset($sku['qty']) || !$sku['qty']) {
                HttpEx('', 50013);
            }
        }

        $res = $this->ordersService->place($merchantId, $mp, $uid, $skuList, $languageKey);

        return ApiResponse::returnRes($res);
    }

    public function list(Request $request) {
        $uid = $request->user->id;
        $languageKey = $request->languageKey;
        $page = $this->request->param('page', 0);
        $count = $this->request->param('count', 5);

        $orderList = $this->ordersService->list($uid, $page, $count);
        $orderList = $orderList->toArray();
        $orderList = $orderList['data'];

        foreach ($orderList as &$item) {
            $orderId = $item['id'];

            $item = array_merge($item, $this->ordersService->formatOrderItems($orderId, $languageKey));
        }

        return ApiResponse::returnRes($orderList);
    }

    public function detail(Request $request) {
        $languageKey = $request->languageKey;
        $uid = $request->user->id;
        $orderId = $this->request->param('order_id', 0);

        $detail = $this->ordersService->detail($uid, $orderId, $languageKey);

        return ApiResponse::returnRes($detail);
    }

    //核销
    public function writeOff(Request $request) {
        $merchantId = $this->request->merchant->id;
        $staffUid = $request->user->id;
        $orderNo = $this->request->param('code', '');

        $order = $this->ordersService->getByOrderNo($orderNo);

        if (!$order) {
            HttpEx('', 50014);
        }

        if ($order['merchant_id'] != $merchantId) {
            HttpEx('', 50016);
        }

        if (!$this->ordersService->canUse($order)) {
            HttpEx('请检查订单状态、可使用时间段');
        }

        $staff = $this->usersService->getById($staffUid);
        if (!$staff
            || $staff['type'] == Users::TYPE_USER
            || $staff['merchant_id'] != $merchantId
            || $staff['del_flag'] == Users::FORBIDDEN) {
            HttpEx('', 50016);
        }

        $this->ordersService->writeOff($order['id'], $staffUid);

        return ApiResponse::returnRes(true);
    }

    public function payCallback(Orders $orders, OrdersService $ordersService) {
//        $GLOBALS['HTTP_RAW_POST_DATA'] = '<xml><appid><![CDATA[wxf1c3ef6d9318c072]]></appid>
//<bank_type><![CDATA[GDB_CREDIT]]></bank_type>
//<cash_fee><![CDATA[1]]></cash_fee>
//<fee_type><![CDATA[CNY]]></fee_type>
//<is_subscribe><![CDATA[N]]></is_subscribe>
//<mch_id><![CDATA[1696032179]]></mch_id>
//<nonce_str><![CDATA[50e8dcf4dfa619d265cf3dd03ffdc936]]></nonce_str>
//<out_trade_no><![CDATA[0241119002216012]]></out_trade_no>
//<result_code><![CDATA[SUCCESS]]></result_code>
//<return_code><![CDATA[SUCCESS]]></return_code>
//<sign><![CDATA[47F6F203305F76E24B0D4C028978B9A0]]></sign>
//<time_end><![CDATA[20210831010228]]></time_end>
//<total_fee>1</total_fee>
//<trade_type><![CDATA[NATIVE]]></trade_type>
//<transaction_id><![CDATA[4200001165202108316579223755]]></transaction_id>
//</xml>';

        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
        $return = [
            'return_code' => 'FAIL',
            'return_msg' => ''
        ];
        $weChatPayUtils = app(PayUtils::class);

        if($data) {
            $data = $weChatPayUtils->fromXml($data);

            if ($data['return_code']) {
                if ($weChatPayUtils->validateOrder($data)) {
                    $outTradeNo = $data['out_trade_no'];
                    $order = $orders->where(['order_no' => $outTradeNo])->find();

                    if ($order && $order['pay_status'] != Orders::PAY_STATUS_UNPAID) {
                        $return['return_code'] = "SUCCESS";
                        echo $weChatPayUtils->toXml($return);
                        exit;
                    }

                    $checkOrderRes = $weChatPayUtils->checkOrder($outTradeNo);

                    if (array_key_exists("return_code", $checkOrderRes)
                        && array_key_exists("result_code", $checkOrderRes)
                        && $checkOrderRes["return_code"] == "SUCCESS"
                        && $checkOrderRes["result_code"] == "SUCCESS"
                        && $checkOrderRes['trade_state'] == "SUCCESS") {
;
                        $validTime = $ordersService->calcOrderValidTime($order['id']);
                        $validFrom = $validTime['validFrom'];
                        $validEnd = $validTime['validEnd'];

                        $order->save([
                            'pay_status' => Orders::PAY_STATUS_PAID,
                            'paid_time' => date('Y-m-d H:i:s'),
                            'transaction_id' => $data['transaction_id'],
                            'valid_from' => $validFrom,
                            'valid_end' => $validEnd
                        ]);

                        $return['return_code'] = "SUCCESS";
                    } else {
                    }

                } else {
                    $data['return_msg'] = "sign校验失败";
                }
            }
        }

        echo $weChatPayUtils->toXml($return);
        exit;
    }

    public function refundCallback(Orders $orders) {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");;

//          $data = '<xml><appid><![CDATA[wxd44b92011bdeefe6]]></appid>
//         	<mch_id><![CDATA[1613144163]]></mch_id>
//         	<nonce_str><![CDATA[aaeb6dcc6a3009755a0c7b33ab7aaa97]]></nonce_str>
//         	<req_info><![CDATA[eCZshjbLxTkuEumP1jAfyfBIBsukbDTzUdbAIkUIjXEIiOISQKy774w43aImbOsJy4rXpn147VCZ5anWYPTdlaHH0h00Cm5TdyndbEQtrKzPlrZbdh8QI97EQf+Za00Rb2QKicYtjRt+7o8d/d6m3cV2FAkNQ/y/GapMnuLdUI9s5m8GjYiDavVHYEi3A4WMUOsMLjoU8WgdcFlcjWNKOYq2klmONesb/ZafQC47+dOXAmDelHfEfVZPfK8FnnnBW00PGjc6QyqkEX9574/caYoi96quzDELsq94zGpaytpYoAvG+G+hs6/4CG6Zf42IK8fJw3V+QYKeaYkn/bOB5wL+/DpYUrXc1oRZQ8BzukQzc1P7VN/3rJJTv1nyZUQ88Bn1NbQ2kA64LUG5LZmq4S3sU1iU8LfnAfFjsn1sa3F+Mb6U+jE1M9zaQf1lbEI/AicpPYkRbi8+7Di1NSIL86pl41Il/D3ftwE5s5AzaMKR1JsrDEDXrvsjEMcAilpqrtQ43NjZvGzyLin164b+3OYT7wp0vgD+CItpgpQOXmY3E2zftnCZJLN1vLxLsdtUMgIkvgAJfPrlTwZ5a94Kr1PRu0zO+bje760BZjenSuBhRYvUahmY3MXGiHGbSbV7zhvWgUazsaI4V2Znwxy67wMdBOF3fEFG1Jsrern0JzQOkp3EUK8NXMLChIJPyjJpsukztp8cVlzd8GheKauSQKxFaZMp5aqv7U7fINGYZz0vcAfd79tq7y5pEzArfOdzSv9fZ3jEXBmnK2K62lxEovJzdaah6TFn+O9otup1jOApEyorkUDPnElgaCFreZLKZBOXE84OvrYSctrHZ94qzChzSE5sfvUHNuATMHzym+TOfx/UDBc7OLKbUlGCP6xR13odcE+fwef26gGcHrywLol9HV5RuKh8zYubcqkV5XYSwPHkikGPlDQGX6DrmviSlU5wk/cZcc8rN/JTPRVFeZ74IlN/lGosfTjq5kVCnpdnqONbWou8lazkP4dcBk0nd8TnlhYdgKB0iCwweUGDgym791qH6tXSiJp2dipzB8qS1y4WZF595d8WJ8kFGHls]]></req_info>
//         	<return_code><![CDATA[SUCCESS]]></return_code>
//         </xml>';

        $return = [
            'return_code' => 'SUCCESS',
            'return_msg' => ''
        ];

        $weChatPayUtils = app(PayUtils::class);

        if($data) {
            $data = $weChatPayUtils->fromXml($data);
        }

        if($data && $data['return_code'] == 'SUCCESS') {

            $reqInfo = $weChatPayUtils->decodeReqInfo($data['req_info']);

            if ($reqInfo && $reqInfo['out_refund_no'] && $reqInfo['out_trade_no']) {

                $order = $orders->where(['order_no' => $reqInfo['out_trade_no']])->find();

                if ($order && $order['pay_status'] == Orders::PAY_STATUS_REFUNDED) {
                    $return['return_code'] = "SUCCESS";
                    echo $weChatPayUtils->toXml($return);
                    exit;
                }

                $order->save([
                    'pay_status' => Orders::PAY_STATUS_REFUNDED,
                    'status' => Orders::STATUS_CANCELLED
                ]);
            }
        }

        echo $weChatPayUtils->toXml($return);
        exit;
    }

}