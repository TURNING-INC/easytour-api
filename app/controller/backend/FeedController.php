<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Tools;
use app\model\Users;
use app\service\backend\FeedsService;
use app\service\front\UsersService;
use think\App;
use think\Request;

class FeedController extends BaseController
{
    private $feedsService;
    private $usersService;

    public function __construct(
        App $app,
        FeedsService $feedsService,
        UsersService $usersService
    )
    {
        parent::__construct($app);
        $this->feedsService = $feedsService;
        $this->usersService = $usersService;
    }

    public function list(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $type = $this->request->param('type', NULL);
        $keyword = $this->request->param('keyword', '');
        $username = $this->request->param('author_name', '');
        $delFlag = $this->request->param('del_flag', NULL);
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 20);

        $list = $this->feedsService->list($merchantId, $type, $keyword, $username, $delFlag, $page, $count);

        foreach ($list->items() as &$item) {
            $item->cover = json_decode($item->cover) ?? [];
        }

        return ApiResponse::returnRes(['total' => $list->total(), 'list' => $list->items()]);
    }

    public function detail(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $feedId = $this->request->param('feed_id', 0);

        if (!$feedId) {
            HttpEx('参数缺失');
        }

        $detail = $this->feedsService->detail($feedId);

        $user = $this->usersService->getById($detail['uid']);
        $detail['can_edit'] = !(($user['merchant_id'] != $merchantId || $user['type'] == Users::TYPE_USER));

        return ApiResponse::returnRes($detail);
    }

    public function save(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $feedId = $this->request->param('feed_id', 0);
        $cover = $this->request->param('cover', '');
        $banner = $this->request->param('banner', '');
        $zhCnTitle = $this->request->param('zh_cn_title', '');
        $zhHkTitle = $this->request->param('zh_hk_title', '');
        $enTitle = $this->request->param('en_title', '');
        $zhCnContent = $this->request->param('zh_cn_content', '');
        $zhHkContent = $this->request->param('zh_hk_content', '');
        $enContent = $this->request->param('en_content', '');
        $uid = $this->request->param('uid', 0);
        $type = $this->request->param('type', 0);
        $weight = $this->request->param('weight', 0);
        $delFlag = $this->request->param('del_flag', 0);

        $feed = $this->feedsService->getById($feedId);

        if ($feed && $feed['merchant_id'] != $merchantId) {
            HttpEx('数据错误');
        }

        if (!$uid) {
            HttpEx('请选择发布用户');
        }

        $user = $this->usersService->getById($uid);
        if ($user['merchant_id'] != $merchantId || $user['type'] == Users::TYPE_USER) {
            $feedId ? HttpEx('无权修改') : HttpEx('该用户非员工');
        }

//        $zhCnContent = array_filter(json_decode($zhCnContent, true) ?? []);
//        $zhHkContent = array_filter(json_decode($zhHkContent, true) ?? []);
//        $enContent = array_filter(json_decode($enContent, true) ?? []);

        $cover = array_filter(json_decode($cover, true) ?? []);

        if (!$cover) {
            HttpEx('封面缺失');
        }

        if (!$zhCnTitle || !$zhHkTitle || !$enTitle) {
            HttpEx('标题缺失不完整');
        }

        if (!$zhCnContent || !$zhHkContent || !$enContent) {
            HttpEx('内容缺失不完整');
        }

        $res = $this->feedsService->saveFeed($feedId, [
            'merchant_id' => $merchantId,
            'cover' => json_encode($cover),
            'banner' => $banner,
            'zh_cn_title' => $zhCnTitle,
            'zh_hk_title' => $zhHkTitle,
            'en_title' => $enTitle,
            'uid' => $uid,
            'type' => $type,
            'weight' => $weight,
            'del_flag' => $delFlag
        ]);

        $feedId = $feedId ?: $res;

        $this->feedsService->saveFeedContent($feedId, [
            'feed_id' => $feedId,
            'zh_cn_content' => $zhCnContent , //json_encode($zhCnContent, JSON_UNESCAPED_UNICODE),
            'zh_hk_content' => $zhHkContent, //json_encode($zhHkContent, JSON_UNESCAPED_UNICODE),
            'en_content' => $enContent, //json_encode($enContent),
        ]);

        return ApiResponse::returnRes(true);
    }

    public function replyList() {
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 10);
        $feedId = $this->request->param('feed_id', 0);
        $threadId = $this->request->param('thread_id', 0);

        if (!$feedId) HttpEx('参数缺失');
        $feed = $this->feedsService->getById($feedId);

        if (!$feed) {
            HttpEx('数据不存在');
        }

        $results = $this->feedsService->replyList($feedId, $threadId, $page, $count);
        $results = $results->toArray();
        $list = $results['data'];
        return ApiResponse::returnRes($list);
    }
}