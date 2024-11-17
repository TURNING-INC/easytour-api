<?php

namespace app\service\backend;

use app\BaseController;
use app\model\Orders;
use app\model\Users;
use think\App;

class UsersService extends BaseController
{
    private $users;

    public function __construct(
        App $app,
        Users $users
    )
    {
        parent::__construct($app);
        $this->users = $users;
    }

    public function getByPhone($merchantId, $phone) {
        return $this->users->where([['merchant_id', '=', $merchantId], ['phone', '=', $phone]])->find();
    }

    public function getById($id, $field='*') {
        return $this->users->field($field)->find($id);
    }

    public function getStaffUsers($merchantId, $field='*') {
        return $this->users
                ->field($field)
                ->where([['merchant_id', '=', $merchantId], ['type', '<>', Users::TYPE_USER]])
                ->order('type asc')
                ->select();
    }

    public function list($merchantId, $keyword, $page=1, $count=20) {
        $where = [];
        $where[] = "u.merchant_id = {$merchantId}";

        if ($keyword) {
            $where[] = "(u.username LIKE '%{$keyword}%' OR u.phone LIKE '%{$keyword}%')";
        }

        $orderStatus = Orders::STATUS_NORMAL;
        $orderPayStatus = Orders::PAY_STATUS_PAID;

        $where = implode( ' AND ', $where);
        return $this->users
                    ->alias('u')
                    ->leftJoin('orders o', "o.uid = u.id AND o.status = {$orderStatus} AND o.pay_status = {$orderPayStatus}")
                    ->field('u.id, u.phone, u.username, u.avatar_url, 
                                    count(o.id) as total_paid_num, sum(IFNULL(o.pay_amount, 0)) as total_paid_amount')
                    ->where($where)
                    ->order('u.id')
                    ->paginate($count,false, ['page'=>$page]);
    }

}