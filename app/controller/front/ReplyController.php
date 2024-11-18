<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Tools;
use app\model\Likes;
use app\service\front\FeedsService;
use app\service\front\LikesService;
use app\service\front\RepliesService;
use think\Request;
use think\App;

class ReplyController extends BaseController
{
    private $repliesService;
    private $feedsService;
    private $likesService;

    public function __construct(
        App $app,
        RepliesService $repliesService,
        FeedsService $feedsService,
        LikesService $likesService
    )
    {
        parent::__construct($app);
        $this->repliesService = $repliesService;
        $this->feedsService = $feedsService;
        $this->likesService = $likesService;
    }

    public function list() {
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 5);
        $feedId = $this->request->param('feed_id', 0);
        $threadId = $this->request->param('thread_id', 0);

        if (!$feedId) HttpEx('', 50013);
        $feed = $this->feedsService->getById($feedId);

        if (!$feed) {
            HttpEx('', 50014);
        }

        if ($threadId && !$this->repliesService->getById($threadId)){
            HttpEx('', 50014);
        }

        $uid = 0;
        if ($token = $this->request->param('token', "")) {
            $tokenRes = Tools::decodeFrontToken($token);
            $uid = 1;$tokenRes['uid'] ?? 0;
        }

        $results = $this->repliesService->list($feedId, $threadId, $uid, $page, $count);
        $results = $results->toArray();
        $list = $results['data'];
        return ApiResponse::returnRes($list);
    }

    public function add(Request $request) {
        $uid = $request->user->id;
        $feedId = $this->request->param('feed_id', "0");
        $replyToId = $this->request->param('reply_to_id', 0);
        $content = $this->request->param('content', "");

        if (!$feedId) HttpEx('', 50013);
        $feed = $this->feedsService->getById($feedId);

        if (!$feed) {
            HttpEx('', 50014);
        }

        $replyTo = $this->repliesService->getById($replyToId);
        if ($replyToId && !$replyTo){
            HttpEx('', 50014);
        }

        if (!$content) {
            HttpEx('', 50017);
        }

        $resultId = $this->repliesService->add($feedId, $feed['uid'], $replyToId, $uid, $content);

        return ApiResponse::returnRes((bool)$resultId);
    }

    public function del(Request $request) {
        $uid = $request->user->id;
        $id = $this->request->param('reply_id', "0");

        if (!$id) {
            HttpEx('', 50013);
        }

        $reply = $this->repliesService->getById($id);

        if (!$reply) {
            HttpEx('', 50014);
        }

        if ($uid != $reply['uid']) {
            HttpEx('', 50016);
        }

        if ($reply['del_flag']) {
            return ApiResponse::returnRes(true);
        }

        $result = $this->repliesService->del($id);

        return ApiResponse::returnRes((bool)$result);
    }

    public function like(Request $request) {
        $uid = $request->user->id;
        $replyId = $this->request->param('reply_id', 0);

        if (!$replyId) {
            HttpEx('', 50013);
        }

        $reply = $this->repliesService->getById($replyId);
        if (!$reply) {
            HttpEx('', 50014);
        }

        //检查是否已经like
        if ($this->likesService->isLike($uid, $replyId, Likes::TYPE_REPLY)) {
            return ApiResponse::returnRes(true);
        }

        $result = $this->repliesService->like($uid, $replyId);

        return ApiResponse::returnRes((bool)$result);
    }

    public function cancelLike(Request $request) {
        $uid = $request->user->id;
        $replyId = $this->request->param('reply_id', 0);

        if (!$replyId) {
            HttpEx('', 50013);
        }

        if (!$this->repliesService->getById($replyId)) {
            HttpEx('', 50014);
        }

        //检查是否已经dislike
        if (!$this->likesService->isLike($uid, $replyId, Likes::TYPE_REPLY)) {
            return ApiResponse::returnRes(true);
        }

        $result = $this->repliesService->cancelLike($uid, $replyId);

        return ApiResponse::returnRes((bool)$result);
    }
}