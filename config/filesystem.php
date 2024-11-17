<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'local'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 更多的磁盘配置信息
        'oss' => [
            'type' => 'aliyun',
            //阿里云主账号AccessKey拥有所有API的访问权限，风险很高。
            //强烈建议您创建并使用RAM账号进行API访问或日常运维，请登录 https://ram.console.aliyun.com 创建RAM账号。
            'accessId' => 'LTAI5tAfM1pwAFen3FPsHb5k',
            'accessSecret' => '6WV3I07aSt7sMHaoVbRWag6IE496M8',
            //存储空间名称
            'bucket' => 'golf-room',
            //节点
            'endpoint' => 'https://oss-cn-beijing.aliyuncs.com',
            //目录
            'dir' => 'wx-golf-room/upload/',    //默认
            'user_avatar_dir' => 'wx-golf-room/user/avatar/',   //用户头像
            'user_bg_dir' => 'wx-golf-room/user/bg/',   //用户背景
            //url
            'url' => 'https://golf-room.oss-cn-beijing.aliyuncs.com/',
        ],
    ],
];
