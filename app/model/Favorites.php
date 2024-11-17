<?php

namespace app\model;

use think\Model;

class Favorites extends Model
{
    const TYPE_FEED = 0;
    const TYPE_PRODUCT = 1;

    const VALID = 1;
    const INVALID = 0;
}