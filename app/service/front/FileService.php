<?php

namespace app\service\front;

use app\BaseController;
use OSS\OssClient;
use think\App;
use think\facade\Filesystem;
use think\facade\Log;

class FileService extends BaseController
{
    private $allowType = ['jpg', 'jpeg', 'gif', 'png', 'pdf', 'pptx', 'mp4'];
    protected $ossClient;

    public function __construct(
        App $app
    )
    {
        parent::__construct($app);

        try {
            $this->ossClient = new OssClient(
                Filesystem::getDiskConfig('oss', 'accessId'),
                Filesystem::getDiskConfig('oss', 'accessSecret'),
                Filesystem::getDiskConfig('oss', 'endpoint')
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            HttpEx($e->getMessage());
        }
    }

    /**
     * 上传到oss
     * @param $file
     * @param string $dir
     * @return string
     */
    public function uploadOss($file, $dir = 'dir')
    {
        //文件格式
        $ext = $file['name'] == 'blob'
            ? 'jpg'
            : strtolower(substr($file['name'], strrpos($file['name'], '.') + 1));

        if (!in_array($ext, $this->allowType)) {
            HttpEx('该文件格式不允许上传');
        }

        //上传对象
        $object = Filesystem::getDiskConfig('oss', $dir) . date('Ymd') . '/' . uniqid(mt_rand()) . '.' . $ext;

        //上传
        try {
            $this->ossClient->uploadFile(
                Filesystem::getDiskConfig('oss', 'bucket'),
                $object,
                $file['tmp_name']
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            HttpEx($e->getMessage());
        }

        return Filesystem::getDiskConfig('oss', 'url') . $object;
    }
}