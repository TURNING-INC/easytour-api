<?php

namespace app\model;

use think\Model;

class Likes extends Model
{
    const TYPE_FEED = 0;
    const TYPE_REPLY = 1;
}