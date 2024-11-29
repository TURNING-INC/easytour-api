<?php

namespace app\service\backend;

use app\BaseController;
use app\model\FeedContent;
use app\model\Feeds;
use app\model\Replies;
use app\model\Users;
use think\App;

class FeedsService extends BaseController
{
    private $feeds;
    private $feedContent;
    private $replies;
    private $users;

    public function __construct(
        App $app,
        Feeds $feeds,
        FeedContent $feedContent,
        Replies $replies,
        Users $users
    )
    {
        parent::__construct($app);
        $this->feeds = $feeds;
        $this->feedContent = $feedContent;
        $this->replies = $replies;
        $this->users = $users;
    }

    public function list($merchantId, $type, $keyword, $username, $delFlag, $page, $count) {
        $where[] = "f.merchant_id = {$merchantId}";

        if ($type !== NULL) {
            $where[] = "f.type = {$type}";
        }

        if ($keyword) {
            $where[] = "f.`zh_cn_title` LIKE '%{$keyword}%'";
        }

        if ($username) {
            $where[] = "u.username LIKE '%{$username}%'";
        }

        if ($delFlag !== NULL) {
            $where[] = "f.del_flag = {$delFlag}";
        }

        $where = implode(' AND ', $where);
        return $this->feeds
            ->alias('f')
            ->leftJoin('users u', 'u.id = f.uid')
            ->field("f.id, f.cover, f.`zh_cn_title` as title, f.like_count, f.reply_count, f.del_flag, u.username as author_name")
            ->where($where)
            ->group("f.id")
            ->order("f.weight desc, f.id desc")
            ->paginate($count,false, ['page'=>$page]);
    }

    public function getById($feedId) {
        return $this->feeds->find($feedId);
    }

    public function detail($feedId) {
        $detail = $this->feeds->find($feedId);

        if (!$detail) {
            return [];
        }
        $detail['cover'] = json_decode($detail['cover']) ?? [];
        $detail['banner'] = json_decode($detail['banner']) ?? [];

        $content = $this->feedContent
                    ->where(['feed_id' => $feedId])
                    ->field('zh_cn_content, zh_hk_content, en_content')
                    ->find();

//        if ($content) {
//            $content['zh_cn_content'] = json_decode($content['zh_cn_content']);
//            $content['zh_hk_content'] = json_decode($content['zh_hk_content']);
//            $content['en_content'] = json_decode($content['en_content']);
//        }
        $detail['content'] = $content;

        $user = $this->users->field('id, username')->find($detail['uid']);
        $detail['user'] = $user;

        return $detail;
    }

    public function saveFeed($feedId, $data) {
        if (!$feedId) {
            return $this->feeds->insertGetId($data);
        } else {
            $this->feeds->where(['id' => $feedId])->save($data);
            return true;
        }
    }

    public function saveFeedContent($feedId, $content) {
        $data = $this->feedContent->where(['feed_id' => $feedId])->find();

        if (!$data) {
            $this->feedContent->insert($content);
        } else {
            $data->save($content);
        }

        return true;
    }

    public function replyList($feedId, $threadId, $page, $count) {
        return $this->replies->alias('reply')
            ->field("reply.id, reply.content, reply.like_count, reply.reply_count, reply.created_at,
                      replier.username, replier.avatar_url ")
            ->leftJoin("users replier", 'replier.id=reply.uid')
            ->where([
                'reply.feed_id' => $feedId,
                'reply.thread_id' => $threadId,
                'reply.del_flag' => 0,
            ])
            ->paginate($count,false, ['page'=>$page]);
    }
}