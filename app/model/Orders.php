<?php

namespace app\model;

use think\Model;

class Orders extends Model
{
    const STATUS_NORMAL = 0; //正常
    const STATUS_CANCELLED = 1; //已取消（用户自己取消）
    const STATUS_EXPIRED = 2; //已过期（错过有效期）
    const STATUS_EXPIRED_UNAVAILABLE = 3; //不可用（退款导致的不可用）

    const PAY_STATUS_UNPAID = 0; //未付款
    const PAY_STATUS_PAID = 1; //已付款
    const PAY_STATUS_REFUNDING = 2; //退款中
    const PAY_STATUS_REFUNDED = 3; //已退款

    const USE_STATUS_UNUSED = 0; //未使用
    const USE_STATUS_USED = 1;  //已使用

    const COMPLETE_PAYMENT_WITHIN = 10;//60 * 5; //订单须在 5分钟 内支付，否则失效
}