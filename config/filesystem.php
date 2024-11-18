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
            'accessId' => 'LTAI5tMXj1iTdeM4nRGV8C9c',
            'accessSecret' => 'R2r8EIogWR2eIsLH87aJjegGkSgUQd',
            //存储空间名称
            'bucket' => 'easytour',
            //节点
            'endpoint' => 'https://oss-cn-beijing.aliyuncs.com',
            //目录
            'dir' => 'upload/',    //默认
            'user_avatar_dir' => 'user/avatar/',   //用户头像
            //url
            'url' => 'https://easytour.oss-cn-beijing.aliyuncs.com/',
        ],
    ],
];
