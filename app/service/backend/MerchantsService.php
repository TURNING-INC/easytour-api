<?php

namespace app\service\backend;

use app\BaseController;
use app\model\Merchants;
use think\App;

class MerchantsService extends BaseController
{
    private $merchants;

    public function __construct(
        App $app,
        Merchants $merchants
    )
    {
        parent::__construct($app);
        $this->merchants = $merchants;
    }

    public function getById($id) {
        return $this->merchants->find($id);
    }

    public function save($id, $data) {
        return $this->merchants->where(['id' => $id])->save($data);
    }

}