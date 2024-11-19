<?php

namespace app\service\front;

use app\BaseController;
use app\model\Favorites;
use app\model\FeedContent;
use app\model\Feeds;
use app\model\Likes;
use app\service\front\LikesService;
use app\service\front\FavoritesService;
use think\App;
use think\facade\Db;

class FeedsService extends BaseController
{
    private $feeds;
    private $feedContent;
    private $likesService;
    private $favoritesService;

    public function __construct(
        App $app,
        Feeds $feeds,
        FeedContent $feedContent,
        LikesService $likesService,
        FavoritesService $favoritesService
    )
    {
        parent::__construct($app);
        $this->feeds = $feeds;
        $this->feedContent = $feedContent;
        $this->likesService = $likesService;
        $this->favoritesService = $favoritesService;
    }

    public function list($merchantId, $type, $languageKey='zh_cn', $page=1, $count=5) {
        $where[] = "f.merchant_id = {$merchantId}";

        if ($type !== NULL) {
            $where[] = "f.type = {$type}";
        }

        $where = implode(' AND ', $where);
        return $this->feeds
            ->alias('f')
            ->leftJoin('users u', 'u.id = f.uid')
            ->field("f.id, f.cover, f.`{$languageKey}_title` as title, f.like_count, f.reply_count, f.del_flag, u.username as author")
            ->where($where)
            ->group("f.id")
            ->order("f.weight desc, f.id desc")
            ->paginate($count,false, ['page'=>$page]);
    }

    public function getById($id, $field='*') {
        return $this->feeds->field($field)->find($id);
    }

    public function getContent($feedId, $field='*') {
        return $this->feedContent->where(['feed_id' => $feedId])->field($field)->find();
    }

    public function like($uid, $id) {
        if($this->likesService->like($uid, $id, Likes::TYPE_FEED)) {
            Db::query("update feeds set like_count=like_count+1 where id={$id}");
        }

        return true;
    }

    public function cancelLike($uid, $id) {
        if ($this->likesService->cancelLike($uid, $id, Likes::TYPE_FEED)) {
            Db::query("update feeds SET `like_count` = 
                    CASE
                        WHEN `like_count` > 0 THEN `like_count` - 1
                        ELSE `like_count`
                    END 
                    where id={$id}");
        }

        return true;
    }

    public function favorite($uid, $id) {
        return $this->favoritesService->favorite($uid, $id, Favorites::TYPE_FEED);
    }

    public function cancelFavorite($uid, $id) {
        return $this->favoritesService->cancelFavorite($uid, $id, Favorites::TYPE_FEED);
    }
}