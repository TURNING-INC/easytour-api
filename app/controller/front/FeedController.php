<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Tools;
use app\model\Favorites;
use app\model\Likes;
use app\service\front\FeedsService;
use app\service\front\UsersService;
use app\service\front\FavoritesService;
use app\service\front\LikesService;
use think\App;
use think\facade\Db;
use think\Request;

class FeedController extends BaseController
{
    private $feedsService;
    private $usersService;
    private $favoritesService;
    private $likesService;

    public function __construct(
        App $app,
        FeedsService $feedsService,
        UsersService $usersService,
        FavoritesService $favoritesService,
        LikesService $likesService
    )
    {
        parent::__construct($app);
        $this->feedsService = $feedsService;
        $this->usersService = $usersService;
        $this->favoritesService = $favoritesService;
        $this->likesService = $likesService;
    }

    public function list() {
        $merchantId = $this->request->merchant->id;
        $languageKey = $this->request->languageKey;
        $type = $this->request->param('type', NULL);
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 5);

        $list = $this->feedsService->list($merchantId, $type, $languageKey, $page, $count);

        foreach ($list->items() as &$item) {
            $item->cover = json_decode($item->cover) ?? [];
        }

        return ApiResponse::returnRes($list->items());
    }

    public function detail() {
        $merchantId = $this->request->merchant->id;
        $languageKey = $this->request->languageKey;
        $feedId = $this->request->param('feed_id', 0);

        if (!$feedId) HttpEx('', 50013);

        $feed = $this->feedsService->getById($feedId, "id, cover, banner, merchant_id, uid, `{$languageKey}_title` as title, 
                                                            like_count, reply_count, created_at");

        if (!$feed || $feed['merchant_id'] != $merchantId) {
            HttpEx('', 50014);
        }

        unset($feed['merchant_id']);

        $feed['cover'] = json_decode($feed['cover']) ?? [];
        $feed['banner'] = json_decode($feed['banner']) ?? [];
        
        $content = $this->feedsService->getContent($feedId, "{$languageKey}_content as content");
        $feed['content'] = $content ? json_decode($content["content"], true) : [];

        $author = $this->usersService->getById($feed['uid'], 'username');
        unset($feed['uid']);
        $feed['author_name'] = $author['username'];

        //初始化是否点赞/收藏了feed
        $feed['is_like'] = false;
        $feed['is_favorite'] = false;
        if ($token = $this->request->param('token', "")) {
            $tokenRes = Tools::decodeFrontToken($token);
            $uid = $tokenRes['uid'] ?? 0;
            $feed['is_like'] = $this->likesService->isLike($uid, $feedId, Likes::TYPE_FEED);
            $feed['is_favorite'] = $this->favoritesService->isFavorites($uid, $feedId, Favorites::TYPE_FEED);
        }

        return ApiResponse::returnRes($feed);
    }

    public function like(Request $request) {
        $uid = $request->user->id;
        $feedId = $this->request->param('feed_id', 0);

        if (!$feedId) HttpEx('', 50013);

        $feed = $this->feedsService->getById($feedId);

        if (!$feed) HttpEx('', 50014);

        if ($this->likesService->isLike($uid, $feedId, Likes::TYPE_FEED)) {
            return ApiResponse::returnRes(true);
        }

        $this->feedsService->like($uid, $feedId);

        return ApiResponse::returnRes(true);
    }

    public function cancelLike(Request $request) {
        $uid = $request->user->id;
        $feedId = $this->request->param('feed_id', 0);

        if (!$this->likesService->isLike($uid, $feedId, Likes::TYPE_FEED)) {
            return ApiResponse::returnRes(true);
        }

        $this->feedsService->cancelLike($uid, $feedId);

        return ApiResponse::returnRes(true);
    }

    public function favorite(Request $request) {
        $uid = $request->user->id;
        $feedId = $this->request->param('feed_id', 0);

        if (!$feedId) HttpEx('', 50013);

        $feed = $this->feedsService->getById($feedId);

        if (!$feed) HttpEx('', 50014);

        if ($this->favoritesService->isFavorites($uid, $feedId, Favorites::TYPE_FEED)) {
            return ApiResponse::returnRes(true);
        }

        $this->feedsService->favorite($uid, $feedId);

        return ApiResponse::returnRes(true);
    }

    public function cancelFavorite(Request $request) {
        $uid = $request->user->id;
        $feedId = $this->request->param('feed_id', 0);

        if (!$this->favoritesService->isFavorites($uid, $feedId, Favorites::TYPE_FEED)) {
            return ApiResponse::returnRes(true);
        }

        $this->feedsService->cancelFavorite($uid, $feedId);

        return ApiResponse::returnRes(true);
    }


}