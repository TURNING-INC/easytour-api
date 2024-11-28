<?php

namespace app\service\front;

use app\BaseController;
use app\lib\PayUtils;
use app\lib\Tools;
use app\lib\Utils;
use app\model\OperateLog;
use app\model\OrderItems;
use app\model\Orders;
use app\model\Sku;
use app\model\Spu;
use app\model\Users;
use think\App;
use think\facade\Db;

class OrdersService extends BaseController
{
    private $orders;
    private $spu;
    private $sku;
    private $orderItems;
    private $operateLog;

    public function __construct(
        App $app,
        Orders $orders,
        Spu $spu,
        Sku $sku,
        OrderItems $orderItems,
        OperateLog $operateLog
    )
    {
        parent::__construct($app);
        $this->orders = $orders;
        $this->spu = $spu;
        $this->sku = $sku;
        $this->orderItems = $orderItems;
        $this->operateLog = $operateLog;
    }

    public function place($merchantId, $mp, $uid, $skuList, $languageKey='zh_cn') {
        //todo 判断开始了没
        try {
            Db::startTrans();
            //检查还有没有库存
            $skuIds = array_column($skuList, 'sku_id');
            $qtyList = array_column($skuList, 'qty', 'sku_id');
            $dbList = $this->sku
                        ->where([['id', 'in', $skuIds]])
                        ->lock(true)
                        ->select();

            $itemList = [];
            $skuSales = [];
            $totalPrice = 0;
            $payBody = [];

            foreach ($dbList as $dbItem) {
                $skuId = $dbItem->id;
                $qty = $qtyList[$skuId];
                $skuSales[$skuId] = $qty;
                $payBody[] = "{$dbItem["{$languageKey}_name"]}×{$qty}";

                if ($dbItem->inventory == 0) {
                    $dbList->count() > 1 ? HttpEx('部分商品已售罄') : HttpEx('商品已售罄');
                }

                if ($dbItem->inventory < $qty) {
                    $dbList->count() > 1 ? HttpEx('部分商品库存不足') : HttpEx('商品库存不足');
                }
                $dbItem->inventory = $dbItem->inventory - $qty;
                $dbItem->save();

                $perPrice = $dbItem->discount_price ?? $dbItem->origin_price;
                $itemTotalPrice = $perPrice * $qty;

                $itemList[] = [
                    'spu_id' => $dbItem['spu_id'],
                    'sku_id' => $skuId,
                    'qty' => $qty,
                    'per_price' => $perPrice,
                    'total_price' => $itemTotalPrice
                ];

                $totalPrice += $itemTotalPrice;
            }

            $orderNo = date('YmdHis').substr('0123456789'.mt_rand(), 0, 3);
            $orderNo = substr($orderNo, 1); //不要第一位，剩余16位

            $body = implode(',', $payBody);

            //$res = $this->callPay($mp, $body, $totalPrice, $orderNo);
            //todo 没有商户可以用，先模拟
$res = ['return_code' => 'SUCCESS', 'return_msg' => 'OK', 'result_code' => 'SUCCESS'];

            if ($res['return_code'] == 'SUCCESS' && $res['return_msg'] == 'OK' && $res['result_code'] == 'SUCCESS') {
                $orderId = $this->orders->insertGetId([
                    'order_no' => $orderNo,
                    'merchant_id' => $merchantId,
                    'uid' => $uid,
                    'pay_amount' => $totalPrice,
                    'order_from' => $mp,
                    'pay_deadline' => time() + Orders::COMPLETE_PAYMENT_WITHIN
                ]);
                $add = ['order_id' => $orderId];

                array_walk($itemList,function(&$value,$k,$add){
                    $value = array_merge($value, $add);
                }, $add);

                $this->orderItems->saveAll($itemList);

                $redis = Tools::redis();

                $redis->setex($orderNo,Orders::COMPLETE_PAYMENT_WITHIN, $orderNo);
            } else {
                HttpEx($res['err_code_des']);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 处理错误，例如记录日志或者返回错误信息

            HttpEx('库存不足');
        }

        return $res;
    }

    public function list($uid, $page, $count) {
        $where[] = "uid = {$uid}";

        $where = implode(' AND ', $where);
        return $this->orders
            ->field('id, pay_amount, status, pay_status, use_status, valid_from, valid_end, pay_deadline')
            ->where($where)
            ->order('id desc')
            ->paginate($count,false, ['page'=>$page]);
    }

    public function getByOrderNo($orderNo) {
        return $this->orders->where(['order_no' => $orderNo])->find();
    }

    public function getById($orderId) {
        return $this->orders->where(['id' => $orderId])->find();
    }

    public function detail($uid, $orderId, $languageKey) {
        $detail = $this->orders
                    ->field('pay_amount, order_no, status, pay_status, use_status, valid_from, valid_end, pay_deadline')
                    ->where([['uid', '=', $uid], ['id', '=', $orderId]])->find();
        if (!$detail) {
            HttpEx('订单不存在');
        }
        $detail = $detail->toArray();
        if ($this->canUse($detail)){
            $detail['qr_code'] = Tools::generateQrCode($detail['order_no']);
        } else {
            unset($detail['order_no']);
        }

        $orderItems = $this->formatOrderItems($orderId, $languageKey);

        return array_merge($detail, $orderItems);
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

    public function salesVolume($spuId, $start='', $end='') {
        $where[] = "oi.spu_id = {$spuId}";
        $where[] = "o.status = " . Orders::STATUS_NORMAL;
        $where[] = "o.pay_status IN (" . implode(',', [Orders::PAY_STATUS_UNPAID, Orders::PAY_STATUS_PAID]) . ")";

        if ($start) {
            $where[] = "o.created_at >= '{$start}'";
        }

        if ($end) {
            $where[] = "o.created_at < '{$end}'";
        }

        $where = implode(" AND ", $where);

        return $this->orders->alias('o')
            ->leftJoin("order_items oi", 'oi.order_id=o.id')
            ->where($where)
            ->sum('oi.qty');
    }

    public function callPay($mp, $body, $totalFee, $outTradeNo) {
        $res = [];

        switch ($mp) {
            case Users::MP_WX:
                $wxPay = app(\app\lib\Wx\PayUtils::class);
                $res = $wxPay->pay($totalFee, $body, $outTradeNo);
                $res = $wxPay->formatRes($res);
                break;
        }

        return $res;
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

    public function writeOff($orderId, $staffUid) {
        $order = $this->orders->find($orderId);

        if ($order->use_status == Orders::USE_STATUS_USED) {
            return true;
        }

        $now = date('Y-m-d H:i:s');
        $order->use_status = Orders::USE_STATUS_USED;
        $order->used_time = $now;
        $order->writeoff_by = $staffUid;

        if ($order->save()) {
            $this->operateLog->log($staffUid, OperateLog::FROM_FRONT, 'writeOff', $orderId,
            OperateLog::TARGET_TYPE_ORDER, ['use_status' => Orders::USE_STATUS_USED, 'used_time' => $now]);
        }

        return true;
    }

    public function calcOrderValidTime($orderId) {
        $orderItem = $this->orderItems->find($orderId);
        $spuId = $orderItem['spu_id'];
        $spu = $this->spu->find($spuId);
        $validFrom = '';
        $validEnd = '';

        if ($spu['valid_period']) {
            $validPeriod = json_decode($spu['valid_period'], true);
            list($validFrom, $validEnd) = $validPeriod;
        } else {
            $validDays = $spu['valid_days'];
            $validFrom = date('Y-m-d H:i:s');
            $validEnd = date("Y-m-d H:i:s",strtotime("{$validDays} day"));var_dump("{$validDays} day");
        }

        return ['validFrom' => $validFrom, 'validEnd' => $validEnd];
    }

    public function cancel($orderNo, $returnDetail=false) {
        $order = $this->orders->where(['order_no' => $orderNo])->find();
        $detail = [];

        if ($order && $order->pay_status == Orders::PAY_STATUS_UNPAID && $order->status == Orders::STATUS_NORMAL) {
            $detail[] = "取消未支付订单{$orderNo}...";
            try {
                Db::startTrans();

                $orderItems = $this->orderItems->where(['order_id' => $order['id']])->select();

                foreach ($orderItems as $item) {
                    $skuId = $item['sku_id'];
                    $qty = $item['qty'];
                    $sku = $this->sku
                        ->where(['id' => $skuId])
                        ->lock(true)
                        ->find();
                    $sku->inventory = $sku->inventory + $qty;
                    $sku->save();

                    $detail[] = "sku({$skuId})恢复库存数：{$qty}";
                }

                $order->status = Orders::STATUS_EXPIRED;
                $order->save();

                $detail[] = "订单{$orderNo} status置为" . Orders::STATUS_EXPIRED;
                Db::commit();

                if ($returnDetail) {
                    return $detail;
                } else {
                    return true;
                }

            } catch (\Exception $e) {
                Db::rollback();

                HttpEx($e->getMessage());
            }
        }
    }

}