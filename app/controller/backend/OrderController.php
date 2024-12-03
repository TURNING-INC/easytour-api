<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\model\Orders;
use app\service\backend\OrdersService;
use app\service\backend\UsersService;
use think\App;
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

    public function list(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $orderNo = $this->request->param('order_no', '');
        $uid = $this->request->param('uid', 0);
        $phone = $this->request->param('phone', '');
        $status = $this->request->param('status', NULL);
        $useStatus = $this->request->param('use_status', NULL);
        $payStatus = $this->request->param('pay_status', NULL);
        $startTime = $this->request->param('start_time', '');
        $endTime = $this->request->param('end_time', '');
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 20);

        $result = $this->ordersService->list($merchantId,
                                            $orderNo, $uid, $phone, $status, $useStatus, $payStatus,
                                            $startTime, $endTime,
                                            $page, $count, true);

        if ($result['total']) {

            foreach ($result['list'] as &$item) {
                $item = array_merge($item, $this->ordersService->formatOrderItems($item['id'], 'zh_cn'));
            }
        }

        return ApiResponse::returnRes($result);
    }

    public function detail(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $orderId = $this->request->param('order_id', 0);

        if (!$orderId) {
            HttpEx('参数缺失');
        }

        $field = 'merchant_id, uid, order_no, pay_amount, status, pay_status, paid_time, use_status, used_time, valid_from, valid_end';
        $order = $this->ordersService->getById($orderId, $field);

        if (!$orderId || $order->merchant_id != $merchantId) {
            HttpEx('订单不存在');
        }

        $order = $order->toArray();
        $order['can_use'] = $this->ordersService->canUse($order);
        $order['user'] = $this->usersService->getById($order['uid'], 'avatar_url, username, phone');
        $order = array_merge($order, $this->ordersService->formatOrderItems($orderId, 'zh_cn'));

        return ApiResponse::returnRes($order);
    }

    public function writeOff(Request $request) {
        $adminId = $request->admin->id;
        $merchantId = $request->admin->merchant_id;
        $orderId = $this->request->param('order_id', 0);

        if (!$orderId) {
            HttpEx('参数缺失');
        }

        $order = $this->ordersService->getById($orderId);

        if (!$order || $order->merchant_id != $merchantId) {
            HttpEx('订单不存在');
        }

        if ($order->use_status == Orders::USE_STATUS_USED) {
            HttpEx('订单已核销');
        }

        if (!$this->ordersService->canUse($order)) {
            HttpEx('订单无法核销。请检查订单状态、可使用时间段');
        }

        $this->ordersService->writeOff($orderId, $adminId);

        return ApiResponse::returnRes(true);
    }

    public function refund(Request $request) {
        $adminId = $request->admin->id;
        $merchantId = $request->admin->merchant_id;
        $orderId = $this->request->param('order_id', 0);
        $reason = $this->request->param('reason', '');

        $order = $this->ordersService->getById($orderId);

        if (!$orderId || $order->merchant_id != $merchantId) {
            HttpEx('订单不存在');
        }

        if ($order->pay_status == Orders::PAY_STATUS_REFUNDING){
            HttpEx('订单正在退款');
        }

        if ($order->pay_status == Orders::PAY_STATUS_REFUNDED){
            HttpEx('订单已退款');
        }

        $this->ordersService->refund($orderId, $reason, $adminId);

        return ApiResponse::returnRes(true);
    }
}