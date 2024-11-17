<?php

namespace app\controller\backend;

use app\BaseController;
use app\lib\ApiResponse;
use app\model\Categories;
use app\model\Spu;
use app\service\backend\ProductsService;
use think\App;
use think\facade\Db;
use think\Request;

class ProductController extends BaseController
{
    private $productsService;

    public function __construct(
        App $app,
        ProductsService $productsService
    )
    {
        parent::__construct($app);
        $this->productsService = $productsService;
    }

    public function list(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $type = $this->request->param('type', NULL);
        $keyword = $this->request->param('keyword', '');
        $categoryId = $this->request->param('category_id', 0);
        $delFlag = $this->request->param('del_flag', NULL);
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 20);

        $list = $this->productsService->list($merchantId, $type, $keyword, $categoryId, $delFlag, $page, $count);

        foreach ($list as &$item) {
            $item['cover'] = json_decode($item['cover']);
        }

        return ApiResponse::returnRes(['total' => $list->total(), 'list' => $list->items()]);
    }

    public function detail(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $spuId = $this->request->param('spu_id', 0);

        if (!$spuId) {
            HttpEx('参数缺失');
        }

        $product = $this->productsService->detail($spuId);

        if (!$product || $product['merchant_id'] != $merchantId) {
            HttpEx('商品不存在');
        }

        return ApiResponse::returnRes($product);
    }

    public function saveSpu(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $spuId = $this->request->param('spu_id', 0);

        $spu = $this->productsService->getSpuById($spuId);

        if ($spu && $spu->merchant_id != $merchantId) {
            HttpEx('数据错误');
        }

        $coverUrl = $this->request->param('cover_url', '');
        $zhCnName = $this->request->param('zh_cn_name', '');
        $zhHkName = $this->request->param('zh_hk_name', '');
        $enName = $this->request->param('en_name', '');
        $categoryIds = $this->request->param('category_ids', '');
        $weight = $this->request->param('weight', 0);
        $discountType = $this->request->param('discount_type', NULL);
        $discountStart = $this->request->param('discount_start', NULL);
        $discountEnd = $this->request->param('discount_end', NULL);
        $validDays = $this->request->param('valid_days', 0);
        $validPeriod = $this->request->param('valid_period', '');
        $type = $this->request->param('type', 0);
        $canMc = $this->request->param('can_mc', 0);
        $delFlag = $this->request->param('del_flag', 1);

        $categoryIds = array_filter(explode(',', $categoryIds)) ?? [];
        $validPeriod = array_filter(json_decode($validPeriod, true) ?? []);

        if (!$zhCnName || !$zhHkName || !$enName) {
            HttpEx('请设置名称');
        }

        if ($discountType) {
            if (!$discountStart) HttpEx('请设置折扣开始时间');
            if (!$discountEnd) HttpEx('请设置折扣结束时间');
        }

        if (!$validDays && !$validPeriod) {
            HttpEx('使用有效天数和使用时间必须填写其中一项');
        }

        $data = [
            'merchant_id' => $merchantId,
            'cover_url' => $coverUrl,
            'type' => $type,
            'can_mc' => $canMc,
            'zh_cn_name' => $zhCnName,
            'zh_hk_name' => $zhHkName,
            'en_name' => $enName,
            'weight' => $weight,
            'discount_type' => $discountType,
            'discount_start' => $discountStart,
            'discount_end' => $discountEnd,
            'valid_days' => $validDays,
            'valid_period' => $validPeriod ? implode(',', $validPeriod) : '',
            'del_flag' => !$spuId ? Spu::DELETED: $delFlag
        ];

        $res = $this->productsService->saveSpu($spuId, $data);

        $spuId = !$spuId ? $res : $spuId;
        $this->productsService->saveCategories($spuId, $categoryIds);

        return ApiResponse::returnRes($spuId);
    }

    public function saveSpuDetail(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $spuId = $this->request->param('spu_id', 0);

        $spu = $this->productsService->getSpuById($spuId);

        if (!$spu) {
            HttpEx('商品不存在');
        }

        if ($spu && $spu->merchant_id != $merchantId) {
            HttpEx('数据错误');
        }

        $zhCnDetail = $this->request->param('zh_cn_detail', '');
        $zhHkDetail = $this->request->param('zh_hk_detail', '');
        $enDetail = $this->request->param('en_detail', '');

        $zhCnNotice = $this->request->param('zh_cn_notice', '');
        $zhHkNotice = $this->request->param('zh_hk_notice', '');
        $enNotice = $this->request->param('en_notice', '');

        $zhCnPriceSchedule = $this->request->param('zh_cn_price_schedule', '');
        $zhHkPriceSchedule = $this->request->param('zh_hk_price_schedule', '');
        $enPriceSchedule = $this->request->param('en_price_schedule', '');

        $zhCnLimit = $this->request->param('zh_cn_limit', '');
        $zhHkLimit = $this->request->param('zh_hk_limit', '');
        $enLimit = $this->request->param('en_limit', '');

        $zhCnDetail = array_filter(json_decode($zhCnDetail, true) ?? []);
        $zhHkDetail = array_filter(json_decode($zhHkDetail, true) ?? []);
        $enDetail = array_filter(json_decode($enDetail, true) ?? []);

        if (!$zhCnDetail || !$zhHkDetail || !$enDetail) {
            HttpEx('详情缺失不完整');
        }

        $data = [
            'spu_id' => $spuId,
            'zh_cn_detail' => json_encode($zhCnDetail, JSON_UNESCAPED_UNICODE),
            'zh_hk_detail' => json_encode($zhHkDetail, JSON_UNESCAPED_UNICODE),
            'en_detail' => $enDetail,
            'zh_cn_notice' => $zhCnNotice,
            'zh_hk_notice' => $zhHkNotice,
            'en_notice' => $enNotice,
            'zh_cn_price_schedule' => $zhCnPriceSchedule,
            'zh_hk_price_schedule' => $zhHkPriceSchedule,
            'en_price_schedule' => $enPriceSchedule,
            'zh_cn_limit' => $zhCnLimit,
            'zh_hk_limit' => $zhHkLimit,
            'en_limit' => $enLimit,
        ];

        $this->productsService->saveSpuDetail($spuId, $data);

        return ApiResponse::returnRes(true);
    }

    public function saveSku(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $spuId = $this->request->param('spu_id', 0);
        $skuList = $this->request->param('sku_list', '');
        $skuList = json_decode($skuList, true) ?? [];
        $skuList = array_filter($skuList);

        $spu = $this->productsService->getSpuById($spuId);

        if (!$spu) {
            HttpEx('商品不存在');
        }

        if ($spu && $spu->merchant_id != $merchantId) {
            HttpEx('数据错误');
        }

        if (!$skuList) {
            HttpEx('缺少sku');
        }

        $hasDiscount = $spu->discount_type !== NULL;
        $needParValue = $spu->type == Spu::TYPE_VOUCHER;

        $addList = [];
        $updateList = [];

        foreach ($skuList as $sku) {
            if (!isset($sku['zh_cn_name']) || !isset($sku['zh_hk_name']) || !isset($sku['en_name'])
                || !$sku['zh_cn_name'] || !$sku['zh_hk_name'] || !$sku['en_name']) {
                HttpEx('请设置名称');
            }

            if (!isset($sku['origin_price'])) {
                HttpEx('请设置价格');
            }

            if ($needParValue) {
                if (!isset($sku['par_value'])) HttpEx('请设置券面值');
                if ($sku['par_value'] < $sku['origin_price']) HttpEx('券面值不得小于券价格');
            }

            if ($hasDiscount && !isset($sku['discount_price'])) {
                HttpEx('请设置折扣价');
            }

            if (!$hasDiscount && isset($sku['discount_price']) && is_numeric($sku['discount_price'])) {
                HttpEx('商品没有设置折扣，请删除折扣价');
            }

            if (!isset($sku['inventory']) || !$sku['inventory'] || $sku['inventory'] < 0) {
                HttpEx('请设置库存');
            }


            if (isset($sku['sku_id']) && $sku['sku_id']) {
                $updateList[] = [
                    'id' => $sku['sku_id'],
                    'zh_cn_name' => $sku['zh_cn_name'],
                    'zh_hk_name' => $sku['zh_hk_name'],
                    'zh_en_name' => $sku['en_name'],
                    'origin_price' => $sku['origin_price'],
                    'discount_price' => $sku['discount_price'],
                    'par_value' => $sku['par_value'] ?? NULL,
                    'inventory' => $sku['inventory']
                ];
            } else {
                $addList[] = [
                    'spu_id' => $spuId,
                    'zh_cn_name' => $sku['zh_cn_name'],
                    'zh_hk_name' => $sku['zh_hk_name'],
                    'zh_en_name' => $sku['en_name'],
                    'origin_price' => $sku['origin_price'],
                    'discount_price' => $sku['discount_price'],
                    'par_value' => $sku['par_value'] ?? NULL,
                    'inventory' => $sku['inventory']
                ];
            }
        }

        Db::startTrans();
        try {
            $this->productsService->saveSku($spuId, $updateList, $addList);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 处理错误，例如记录日志或者返回错误信息
            HttpEx('sku保存失败');
        }

        return ApiResponse::returnRes(true);
    }

    public function categoryList(Request $request) {
        $merchantId = $request->admin->merchant_id;

        $list = $this->productsService->categoryList($merchantId);
        return ApiResponse::returnRes($list);
    }

    public function saveCategory(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $categoryId = $this->request->param('category_id', 0);
        $zhCnName = $this->request->param('zh_cn_name', '');
        $zhHkName = $this->request->param('zh_hk_name', '');
        $enName = $this->request->param('en_name', '');
        $sort = $this->request->param('sort', 0);

        $category = $this->productsService->getCategoryById($categoryId);
        if ($category && $category->merchant_id != $merchantId) {
            HttpEx('数据错误');
        }

        if (!$zhCnName || !$zhHkName || !$enName) {
            HttpEx('请设置名称');
        }
        $data = [
            'merchant_id' => $merchantId,
            'zh_cn_name' => $zhCnName,
            'zh_hk_name' => $zhHkName,
            'en_name' => $enName,
            'sort' => $sort
        ];

        $res = $this->productsService->saveCategory($categoryId, $data);

        return ApiResponse::returnRes($res);
    }

    public function delCategory(Request $request) {
        $merchantId = $request->admin->merchant_id;
        $categoryId = $this->request->param('category_id', 0);

        $category = $this->productsService->getCategoryById($categoryId);

        if (!$category) {
            HttpEx('类别不存在');
        }

        if ($category && $category->merchant_id != $merchantId) {
            HttpEx('数据错误');
        }

        if ($category->del_flag != Categories::DELETED) {
            $this->productsService->delCategory($categoryId);
        }

        return ApiResponse::returnRes(true);
    }
}