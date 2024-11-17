<?php

namespace app\service\front;

use app\BaseController;
use app\model\Favorites;
use think\App;

class FavoritesService extends BaseController
{
    private $favorites;

    public function __construct(
        App $app,
        Favorites $favorites
    )
    {
        parent::__construct($app);
        $this->favorites = $favorites;
    }

    public function list($uid, $page=1, $count=5, $targetType=null) {
        $where['uid'] = $uid;
        $where['is_valid'] = Favorites::VALID;

        if ($targetType !== null) {
            $where['target_type'] = $targetType;
        }

        return $this->favorites
            ->where($where)
            ->paginate($count,false, ['page'=>$page]);
    }

    public function isFavorites($uid, $targetId, $targetType) {
        return (bool)$this->favorites->where([['uid', '=', $uid], ['target_id', '=', $targetId], ['target_type', '=', $targetType]])->find();
    }

    public function favorite($uid, $targetId, $targetType) {
        return $this->favorites->insert(['uid' => $uid, 'target_id' => $targetId, 'target_type' => $targetType]);
    }

    public function cancelFavorite($uid, $targetId, $targetType) {
        return $this->favorites->where([['uid', '=', $uid], ['target_id', '=', $targetId], ['target_type', '=', $targetType]])->delete();
    }
}