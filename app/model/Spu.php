<?php

namespace app\model;

use think\Model;

class Spu extends Model
{
    const TYPE_PACKAGE = 0;
    const TYPE_VOUCHER = 1;

    const NORMAL = 0;
    const DELETED = 1;
}