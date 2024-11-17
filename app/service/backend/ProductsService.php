<?php

namespace app\service\backend;

use app\BaseController;
use app\model\Categories;
use app\model\CategorySpu;
use app\model\Sku;
use app\model\Spu;
use app\model\SpuDetail;
use app\service\backend\OrdersService;
use think\App;

class ProductsService extends BaseController
{
    private $spu;
    private $sku;
    private $spuDetail;
    private $categories;
    private $categorySpu;
    private $ordersService;

    public function __construct(
        App $app,
        Spu $spu,
        Sku $sku,
        SpuDetail $spuDetail,
        Categories $categories,
        CategorySpu $categorySpu,
        OrdersService $ordersService
    )
    {
        parent::__construct($app);
        $this->spu = $spu;
        $this->sku = $sku;
        $this->spuDetail = $spuDetail;
        $this->categories = $categories;
        $this->categorySpu = $categorySpu;
        $this->ordersService = $ordersService;
    }

    public function list($merchantId, $type, $keyword, $categoryId, $delFlag, $page, $count) {
        $where[] = "spu.merchant_id = {$merchantId}";

        if ($type !== NULL) {
            $where[] = "spu.type = {$type}";
        }

        if ($keyword) {
            $where[] = "spu.`zh_ch_name` LIKE '%{$keyword}%'";
        }

        if ($categoryId) {
            $where[] = "sp.category_id = {$categoryId}";
        }

        if ($delFlag !== NULL) {
            $where[] = "spu.del_flag = {$delFlag}";
        }

        $where = implode(' AND ', $where);
        return $this->spu
            ->alias('spu')
            ->leftJoin('category_spu sp', 'sp.spu_id = spu.id')
            ->leftJoin('categories c', 'c.id = sp.category_id')
            ->field("spu.id as spu_id, spu.`zh_cn_name` as name, spu.cover, spu.type, spu.del_flag, 
                    GROUP_CONCAT(IFNULL(c.`zh_cn_name`, '')) as categories")
            ->where($where)
            ->group("spu.id")
            ->order("spu.weight desc, spu.id desc")
            ->paginate($count,false, ['page'=>$page]);
    }

    public function detail($spuId) {
        $name = '`zh_cn_name`,`zh_hk_name`,`en_name`';
        $detail = '`zh_cn_detail`,`zh_hk_detail`,`en_detail`';
        $notice = '`zh_cn_notice`,`zh_hk_notice`,`en_notice`';
        $priceSchedule = '`zh_cn_price_schedule`,`zh_hk_price_schedule`,`en_price_schedule`';
        $limit = '`zh_cn_limit`,`zh_hk_limit`,`en_limit`';

        $spu = $this->spu->field("merchant_id, id as spu_id, cover, {$name}, can_mc, weight,
                                       valid_days, valid_period,
                                        type, discount_type, discount_start, discount_end, del_flag")
            ->find($spuId);

        if (!$spu) {
            return [];
        }
        $spu = $spu->toArray();
        $spu['cover'] = json_decode($spu['cover'], true);
        $spu['valid_period'] = json_decode($spu['valid_period'], true);
        $spu['sales_volume'] = $this->ordersService->salesVolume($spuId);

        $spuDetail = $this->spuDetail
            ->field("{$detail}, {$notice}, {$priceSchedule}, {$limit}")
            ->where([['spu_id', '=', $spuId]])
            ->find();

        $spuDetail = $spuDetail->toArray();
        $languageData = $detail . ',' . $notice . ',' .$priceSchedule . ',' . $limit;
        $languageData = explode(',', str_replace('`', '', $languageData));
        foreach ($languageData as $data) {
            if ($spuDetail[$data]) {
                $spuDetail[$data] = json_decode($spuDetail[$data]) ?? [];
            }
        }

        $spu['spu_detail'] = $spuDetail;

        $skuList = $this->sku
            ->field("id as sku_id, {$name}, origin_price, discount_price, par_value, inventory")
            ->where([['spu_id', '=', $spuId], ['del_flag', '=', Sku::NORMAL]])
            ->select();

        $spu['sku_list'] = $skuList;

        return $spu;
    }

    public function getSpuById($spuId) {
        return $this->spu->find($spuId);
    }

    public function saveSpu($spuId, $data) {
        if (!$spuId) {
            return $this->spu->insertGetId($data);
        } else {
            return $this->spu->where(['id' => $spuId])->save($data);
        }
    }

    public function saveCategories($spuId, $categoryIds) {
        $dbData = $this->categorySpu->where(['spu_id' => $spuId])->select();
        $dbCategoryIds = array_column($dbData->toArray(), 'category_id');

        $addCategoryIds = array_diff($categoryIds, $dbCategoryIds);
        $deleteCategoryIds = array_diff($dbCategoryIds, $categoryIds);

        foreach ($addCategoryIds as $addId) {
            $this->categorySpu->insert(['category_id' => $addId, 'spu_id' => $spuId]);
        }

        if ($deleteCategoryIds) {
            $this->categorySpu->where([['spu_id', '=', $spuId], ['category_id', 'in', $deleteCategoryIds]])->delete();
        }

        return true;
    }

    public function saveSpuDetail($spuId, $data) {
        $spuDetail = $this->spuDetail->where(['spu_id' => $spuId])->find();

        if (!$spuDetail) {
            return $this->spuDetail->insert($data);
        } else {
            return $spuDetail->save($data);
        }
    }

    public function saveSku($spuId, $updateList, $addList) {
        $normalStatus = Sku::NORMAL;
        $dbList = $this->sku->where("spu_id = {$spuId} AND del_flag = {$normalStatus}")->field('id')->select();
        $dbIds = array_column($dbList->toArray(), 'id');
        $updateIds = array_column($updateList, 'id');

        //删除
        if($deleteIds = array_diff($dbIds, $updateIds)){
            $this->sku->where([['id', 'in', $deleteIds]])->save(['del_flag' => Sku::DELETED]);
        }

        //更新
        if ($updateList) {
            $this->sku->saveAll($updateList);
        }

        //新增
        if ($addList) {
            $this->sku->saveAll($addList);
        }

        return true;
    }
    public function categoryList($merchantId) {
        return $this->categories
                ->where(['merchant_id' => $merchantId, 'del_flag' => Categories::NORMAL])
                ->order('sort desc')
                ->select();
    }

    public function getCategoryById($categoryId) {
        return $this->categories->find($categoryId);
    }

    public function saveCategory($categoryId, $data) {
        if (!$categoryId) {
            return $this->categories->insertGetId($data);
        } else {
            $this->categories->where(['id' => $categoryId])->save($data);
            return true;
        }
    }

    public function delCategory($categoryId) {
        if ($this->categories->where(['id' => $categoryId])->save(['del_flag' => Categories::DELETED])) {
            $this->categorySpu->where(['category_id' => $categoryId])->delete();
        }

        return true;
    }

}