<?php

namespace app\service\front;

use app\BaseController;
use app\model\Likes;
use think\App;
use think\facade\Db;

class LikesService extends BaseController
{
    private $likes;

    public function __construct(
        App $app,
        Likes $likes
    )
    {
        parent::__construct($app);
        $this->likes = $likes;
    }

    public function isLike($uid, $targetId, $targetType) {
        return (bool)$this->likes->where([['uid', '=', $uid], ['target_id', '=', $targetId], ['target_type', '=', $targetType]])->find();
    }

    public function like($uid, $targetId, $targetType) {
        return $this->likes->insert(['uid' => $uid, 'target_id' => $targetId, 'target_type' => $targetType]);
    }

    public function cancelLike($uid, $targetId, $targetType) {
        return $this->likes->where([['uid', '=', $uid], ['target_id', '=', $targetId], ['target_type', '=', $targetType]])->delete();
    }
}