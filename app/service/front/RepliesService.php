<?php

namespace app\service\front;

use app\BaseController;
use app\model\Likes;
use app\model\Replies;
use think\App;
use think\facade\Db;

class RepliesService extends BaseController
{
    private $replies;
    private $likesService;

    public function __construct(
        App $app,
        Replies $replies,
        LikesService $likesService
    )
    {
        parent::__construct($app);
        $this->replies = $replies;
        $this->likesService = $likesService;
    }

    public function getById($id) {
        return $this->replies->getById($id);
    }

    public function getByIds($ids, $field='*') {
        return $this->replies->where('id in (' . implode(',', $ids) . ')')->field($field)->select();
    }

    public function list($feedId, $threadId, $uid, $page=1, $count=10) {
        return $this->replies->list($feedId, $threadId, $uid, $page, $count);
    }

    public function add($feedId, $replyToUid, $replyToId, $uid, $content) {
        $threadId = 0;
        $level = 1;

        if ($replyToId) {
            $reply = $this->replies->getById($replyToId);
            $threadId = $reply['thread_id'] ?: $replyToId;
            $replyToUid = $reply['uid'];
            $level = $reply['level'] + 1 < Replies::MAX_LEVEL ? $reply['level'] + 1 : Replies::MAX_LEVEL;
        }

        $id = $this->replies->insertGetId([
            'feed_id' => $feedId,
            'thread_id' => $threadId,
            'reply_to_id' => $replyToId,
            'reply_to_uid' => $replyToUid,
            'uid' => $uid,
            'content' => $content,
            'level' => $level
        ]);

        if ($id) {
            Db::query("update feeds set reply_count=reply_count+1 where id={$feedId}");
            $replyToId ? Db::query("update replies set reply_count=reply_count+1 where id={$replyToId}") : null;

            if ($threadId && $threadId != $replyToId) {
                Db::query("update replies set reply_count=reply_count+1 where id={$threadId}");
            }
        }

        return $id;
    }

    public function del($id) {
        $reply = $this->replies->getById($id);
        if ($this->replies->del($id)) {
            Db::query("update feeds SET `reply_count` = 
                    CASE
                        WHEN `reply_count` > 0 THEN `reply_count` - 1
                        ELSE `reply_count`
                    END 
                    where id={$reply['feed_id']}");

            if ($reply['thread_id']) {
                Db::query("update replies SET `reply_count` = 
                    CASE
                        WHEN `reply_count` > 0 THEN `reply_count` - 1
                        ELSE `reply_count`
                    END 
                    where feed_id={$reply['feed_id']}");
            }

            if ($reply['reply_to_id'] && $reply['reply_to_id'] != $reply['thread_id']) {
                Db::query("update replies SET `reply_count` = 
                    CASE
                        WHEN `reply_count` > 0 THEN `reply_count` - 1
                        ELSE `reply_count`
                    END 
                    where feed_id={$reply['feed_id']}");
            }

            //如果删的是有子评论的
            if($delCount = $this->replies->where("thread_id={$id}")->update(['del_flag' => 1])) {
                Db::query("update feeds SET `reply_count` = 
                    CASE
                        WHEN `reply_count` > 0 THEN `reply_count` - {$delCount}
                        ELSE `reply_count`
                    END 
                    where id={$reply['feed_id']}");
            }
        }

        return true;
    }

    public function like($uid, $id) {
        if($this->likesService->like($uid, $id, Likes::TYPE_REPLY)) {
            Db::query("update replies set like_count=like_count+1 where id={$id}");
        }

        return true;
    }

    public function cancellike($uid, $id) {
        if($this->likesService->cancelLike($uid, $id, Likes::TYPE_REPLY)) {
            Db::query("update replies SET `like_count` = 
                    CASE
                        WHEN `like_count` > 0 THEN `like_count` - 1
                        ELSE `like_count`
                    END 
                    where id={$id}");
        }

        return true;
    }
}