<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
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
            HttpEx('该订单无法核销');
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
}