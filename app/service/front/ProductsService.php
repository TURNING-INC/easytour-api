<?php

namespace app\service\front;

use app\BaseController;
use app\model\Categories;
use app\model\Favorites;
use app\model\Likes;
use app\model\Sku;
use app\model\Spu;
use app\model\SpuDetail;
use think\App;

class ProductsService extends BaseController
{
    private $spu;
    private $sku;
    private $spuDetail;
    private $categories;
    private $ordersService;
    private $favoritesService;

    public function __construct(
        App $app,
        Spu $spu,
        Sku $sku,
        SpuDetail $spuDetail,
        Categories $categories,
        OrdersService $ordersService,
        FavoritesService $favoritesService
    )
    {
        parent::__construct($app);
        $this->spu = $spu;
        $this->sku = $sku;
        $this->spuDetail = $spuDetail;
        $this->categories = $categories;
        $this->ordersService = $ordersService;
        $this->favoritesService = $favoritesService;
    }

    public function list($merchantId, $type, $categoryIds, $discountType, $languageKey, $page, $count) {
        $languageName = "{$languageKey}_name";
        $field = "spu.`id` as spu_id, spu.cover, 
                    spu.`{$languageName}` as name, spu.type, 
                    spu.discount_type, spu.discount_start, spu.discount_end,
                    LEFT(pd.`{$languageKey}_detail`, 110) as detail";

        $now = date('Y-m-d H:i:s');
        $where[] = "spu.merchant_id = {$merchantId}";
        $where[] = "(spu.discount_end IS NULL OR spu.discount_end > '{$now}')";
        $where[] = "spu.type = {$type}";
        $where[] = "spu.del_flag = " . Spu::NORMAL;

        $categoryIds = array_filter(explode('，', $categoryIds));
        if ($categoryIds) {
            $categoryIds = implode(',', $categoryIds);
            $where[] = "cp.category_id IN ({$categoryIds})";
        }

        $discountType = array_filter(explode('，', $discountType));
        if ($discountType) {
            $discountType = implode(',', $discountType);
            $where[] = "spu.discount_type IN ({$discountType}) ";
        }

        $where = implode(' AND ', $where);

        return $this->spu::alias('spu')
            ->field($field)
            ->leftJoin("category_spu cp", 'cp.spu_id=spu.id')
            ->leftJoin("spu_detail pd", 'pd.spu_id=spu.id')
            ->where($where)
            ->order('spu.weight desc, spu.id desc')
            ->group('spu.id')
            ->paginate($count,false, ['page'=>$page]);
    }

    public function formatListPrice($spuId) {
        $skuList = $this->sku->where([['spu_id', '=', $spuId], ['del_flag', '=', Sku::NORMAL]])->select();

        if (!$skuList->count()) {
            return [];
        }

        $skuList = $skuList->toArray();
        $result = ['price' => NULL, 'origin_price' => NULL, 'par_value' => NULL];
        $multiplePrices = false;
        $leftInventory = 0;

        //比对最低价格
        foreach ($skuList as $item) {
            $price = $item['discount_price'] !== NULL ? $item['discount_price'] : $item['origin_price'];

            if ($result['price'] !== NULL && $result['price'] != $price) {
                $multiplePrices = true;
            }

            if ($result['price'] === NULl || $result['price'] > $price) {
                $result['price'] = $price;
                $result['origin_price'] = $item['origin_price'];
                $result['par_value'] = $item['par_value'];
            }

            $leftInventory += $item['inventory'];
        }

        $soldNum = $this->ordersService->salesVolume($spuId);
        $total = $soldNum + $leftInventory;
        $result['sold_percent'] = floor(($soldNum / $total) * 100);

        $result['multiple_prices'] = $multiplePrices;

        return $result;
    }

    public function getById($spuId, $feild='*') {
        return $this->spu->field($feild)->find($spuId);
    }

    public function detail($spuId, $languageKey='zh_cn') {
        $languageName = "{$languageKey}_name";
        $languageDetail = "{$languageKey}_detail";
        $languageNotice = "{$languageKey}_notice";
        $languagePriceSchedule = "{$languageKey}_price_schedule";
        $languageLimit = "{$languageKey}_limit";

        $spu = $this->spu->field("id as spu_id, cover, `{$languageName}` as name, can_mc,
                                        type, discount_type, discount_start, discount_end, del_flag")
            ->find($spuId);

        if (!$spu) {
            return [];
        }
        $spu = $spu->toArray();
        //过去6个月销量
        $startTime = date("Y-m-d H:i:s",strtotime("-6 month"));
        $spu['6m_sales_volume'] = $this->ordersService->salesVolume($spuId, $startTime);

        $spuDetail = $this->spuDetail
            ->field("`{$languageDetail}` as detail, `{$languageNotice}` as notice, `{$languagePriceSchedule}` as price_schedule, `{$languageLimit}` as `limit`")
            ->where([['spu_id', '=', $spuId]])
            ->find();

        $spuDetail = $spuDetail ? $spuDetail->toArray() : [];

        $spu = array_merge($spu, $spuDetail);

        $skuList = $this->sku
            ->field("id as sku_id, `{$languageName}` as name, origin_price, discount_price, par_value, inventory")
            ->where([['spu_id', '=', $spuId], ['del_flag', '=', Sku::NORMAL]])
            ->select();

        $spu['sku_list'] = $skuList;

        return $spu;
    }

    public function categoryList($merchantId, $languageKey='zh_cn') {
        $languageName = "{$languageKey}_name";

        return $this->categories
                ->field("id as category_id, `{$languageName}` as name")
                ->where([['merchant_id', '=', $merchantId], ['del_flag', '=', Categories::NORMAL]])
                ->order('sort desc, id desc')
                ->select();
    }

    public function favorite($uid, $id) {
        return $this->favoritesService->favorite($uid, $id, Favorites::TYPE_PRODUCT);
    }

    public function cancelFavorite($uid, $id) {
        return $this->favoritesService->cancelFavorite($uid, $id, Favorites::TYPE_PRODUCT);
    }
}