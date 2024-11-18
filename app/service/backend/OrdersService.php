<?php

namespace app\service\backend;

use app\BaseController;
use app\lib\Wx\PayUtils;
use app\model\OperateLog;
use app\model\OrderItems;
use app\model\Orders;
use app\model\Sku;
use app\model\Spu;
use think\App;

class OrdersService extends BaseController
{
    private $orders;
    private $orderItems;
    private $spu;
    private $sku;
    private $operateLog;

    public function __construct(
        App $app,
        Orders $orders,
        OrderItems $orderItems,
        Spu $spu,
        Sku $sku,
        OperateLog $operateLog
    )
    {
        parent::__construct($app);
        $this->orders = $orders;
        $this->orderItems = $orderItems;
        $this->spu = $spu;
        $this->sku = $sku;
        $this->operateLog = $operateLog;
    }

    public function save($id, $data) {
        return $this->orders->where(['id' => $id])->save($data);
    }

    public function list($merchantId,
                         $orderNo, $uid, $phone, $status, $useStatus, $payStatus,
                         $startTime, $endTime,
                         $page, $count, $includeStatistics=false) {
        $where = [];
        $where[] = "o.merchant_id = {$merchantId}";

        if ($orderNo) {
            $where[] = "o.order_no LIKE '%{$orderNo}%'";
        }

        if ($uid) {
            $where[] = "o.uid = {$uid}";
        }

        if ($phone) {
            $where[] = "u.phone = LIKE '%{$phone}%'";
        }

        if ($status !== NULL) {
            $where[] = "o.status = {$status}'";
        }

        if ($useStatus !== NULL) {
            $where[] = "o.use_status = {$useStatus}'";
        }

        if ($payStatus !== NULL) {
            $where[] = "o.pay_status = {$payStatus}'";
        }

        if ($startTime) {
            $where[] = "o.created_at >= '{$startTime}'";
        }

        if ($endTime) {
            $where[] = "o.created_at < '{$startTime}'";
        }

        $where = implode(' AND ', $where);
        $list = $this->orders
                    ->alias('o')
                    ->join('users u', 'u.id = o.uid')
                    ->field('o.id, o.order_no, u.username, u.phone, 
                            o.status, o.pay_status, o.paid_time, o.use_status, o.created_at')
                    ->where($where)
                    ->paginate($count,false, ['page'=>$page]);

        $list = $list->toArray();
        $result = ['total' => $list['total'], 'list' => $list['data']];

        if ($includeStatistics) {
            $orderStatus = Orders::STATUS_NORMAL;
            $orderPayStatus = Orders::PAY_STATUS_PAID;
            $where .= " AND o.status = {$orderStatus} AND o.pay_status = {$orderPayStatus}";

            $statistics = $this->orders
                ->alias('o')
                ->join('users u', 'u.id = o.uid')
                ->field('count(o.id) as total_paid_num, sum(IFNULL(o.pay_amount, 0)) as total_paid_amount')
                ->where($where)
                ->find();

            $result['statistics'] = $statistics;
        }

        return $result;
    }

    public function getById($id, $field='*') {
        return $this->orders->field($field)->find($id);
    }

    public function formatOrderItems($orderId, $languageKey){
        $orderItems = $this->orderItems->where(['order_id' => $orderId])->select();
        if (!$orderItems->count()) {
            return ['items' => []];
        }
        $orderItems = $orderItems->toArray();

        $skuList = $this->sku->field("id, `{$languageKey}_name` as name")->where([['id', 'in', array_column($orderItems, 'sku_id')]])->select();
        $spuList = $this->spu->field("id, `{$languageKey}_name` as name, cover")->where([['id', 'in', array_column($orderItems, 'spu_id')]])->select();

        $skuList = $skuList->toArray();
        $spuList = $spuList->toArray();

        $skuName = array_column($skuList, 'name', 'id');
        $spuName = array_column($spuList, 'name', 'id');
        $spuCover = array_column($spuList, 'cover', 'id');

        $list = []; //spu_id => spu_detail + order_items

        foreach ($orderItems as $orderItem) {
            $spuId = $orderItem['spu_id'];
            $skuId = $orderItem['sku_id'];

            if(!isset($list[$spuId])) {
                $list[$spuId] = [
                    'name' => $spuName[$spuId],
                    'cover' => json_decode($spuCover[$spuId]) ?? [],
                    'sku_list' => [
                        [
                            'name' => $skuName[$skuId],
                            'per_price' => $orderItem['per_price'],
                            'qty' => $orderItem['qty']
                        ]
                    ]
                ];
            } else {
                $list[$spuId]['sku_list'][] = [
                    'name' => $skuName[$skuId],
                    'per_price' => $orderItem['per_price'],
                    'qty' => $orderItem['qty']
                ];
            }
        }

        return ['items' => array_values($list)];
    }

    public function changeStatus($id, $adminId, $status, $payStatus) {
        $data = [];

        if ($status !== NULL) {
            $data['status'] = $status;
        }

        if ($payStatus !== NULL) {
            $data['pay_status'] = $payStatus;
        }

        if ($data && $this->orders->where(['id' => $id])->save($data)) {
            $this->operateLog->log($adminId, OperateLog::FROM_BACKEND, 'changeStatus', $id,
                OperateLog::TARGET_TYPE_ORDER, $data);
        }

        return true;
    }

    public function writeOff($id, $adminId) {
        $data = [
            'use_status' => Orders::USE_STATUS_USED,
            'used_time' => date('Y-m-d H:i:s'),
        ];

        if($this->orders->where(['id' => $id])->save($data)) {
            $this->operateLog->log($adminId, OperateLog::FROM_BACKEND, 'writeOff', $id,
                            OperateLog::TARGET_TYPE_ORDER, $data);
        }
    }

    public function refund($orderId, $reason, $adminId) {
        $order = $this->orders->find($orderId);
        $orderNo = $order->order_no;
        $transactionId = $order->transaction_id;
        $refundNo = "refund" . date('YmdHis').substr(md5($transactionId.mt_rand()), 0, 6);
        $payAmount = $order->pay_amount;

        $weChatPayUtils = app(PayUtils::class);
        $refundApply = $weChatPayUtils->refund($transactionId, $orderNo, $refundNo, $payAmount * 100, $payAmount * 100, $reason);

        if($refundApply['return_code'] == 'SUCCESS' && $refundApply['result_code'] == 'SUCCESS') {
            //SUCCESS退款申请接收成功，结果通过退款查询接口查询,退款有一定延时，用零钱支付的退款20分钟内到账，银行卡支付的退款3个工作日后重新查询退款状态。
            if (isset($refundApply['err_code'])) {
                HttpEx("退款申请提交失败：（{$refundApply['err_code']}）{$refundApply['err_code_des']}}");
            }

            $data = [
                'status' => Orders::STATUS_CANCELLED,
                'pay_status' => Orders::PAY_STATUS_REFUNDING,
                'refund_no' => $refundNo
            ];
            $order->save($data);

            $this->operateLog->log($adminId, OperateLog::FROM_BACKEND,'refund', $orderId,
                        OperateLog::TARGET_TYPE_ORDER, json_encode($data));

        } else {	//FAIL 提交业务失败
            HttpEx("退款申请提交失败：{$refundApply['return_msg']}");
        }

        return true;
    }

    public function salesVolume($spuId, $start='', $end='') {
        $where[] = "oi.spu_id = {$spuId}";

        if ($start) {
            $where[] = "o.paid_time >= '{$start}'";
        }

        if ($end) {
            $where[] = "o.paid_time < '{$end}'";
        }

        $where = implode(" AND ", $where);

        return $this->orders->alias('o')
            ->leftJoin("order_items oi", 'oi.order_id=o.id')
            ->where($where)
            ->sum('oi.qty');
    }

    public function canUse($order) {
        $order = is_array($order) ? $order : $order->toArray();
        $now = time();

        if (isset($order['status']) && isset($order['pay_status']) && isset($order['use_status'])
            && isset($order['valid_from']) && isset($order['valid_end'])
            && $order['status'] == Orders::STATUS_NORMAL
            && $order['pay_status'] == Orders::PAY_STATUS_PAID
            && $order['use_status'] == Orders::USE_STATUS_UNUSED
            && (strtotime($order['valid_from']) <= $now)
            && (strtotime($order['valid_end']) > $now) ) {
            return true;
        }

        return false;
    }
}