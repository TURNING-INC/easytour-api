<?php

namespace app\model;

use think\Model;

class Replies extends Model
{
    const MAX_LEVEL = 2;

    public function getById($id) {
        return Replies::where(['id' => $id, 'del_flag' => 0])->find();
    }

    public function list($feedId, $threadId, $uid, $page=1, $count=10) {
        $targetType = Likes::TYPE_REPLY;
        return Replies::alias('reply')
            ->field("reply.id, reply.content, reply.like_count, reply.reply_count, reply.created_at,
                      replier.username, replier.avatar_url,
                      IF(like.uid IS NULL,0,1) AS is_like,
                      IF(reply.uid = {$uid}, 1, 0) AS can_delete ")
            ->leftJoin("(select * from likes where uid={$uid} AND target_type={$targetType}) `like`", 'like.target_id=reply.id')
            ->leftJoin("users replier", 'replier.id=reply.uid')
            ->where([
                'reply.feed_id' => $feedId,
                'reply.thread_id' => $threadId,
                'reply.del_flag' => 0,
            ])
            ->paginate($count,false, ['page'=>$page]);
    }

    public function add($feedId, $threadId, $replyToId, $replyToUid, $uid, $content, $level) {
        return Replies::save([
            'feed_id' => $feedId,
            'thread_id' => $threadId,
            'reply_to_id' => $replyToId,
            'reply_to_uid' => $replyToUid,
            'content' => $content,
            'uid' => $uid,
            'level' => $level
        ]);
    }

    public function del($id) {
        return Replies::where("id={$id}")->update(['del_flag' => 1]);
    }
}