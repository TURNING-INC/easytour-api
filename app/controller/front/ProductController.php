<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Tools;
use app\model\Favorites;
use app\model\Spu;
use app\service\front\ProductsService;
use app\service\front\FavoritesService;
use think\App;
use think\Request;

class ProductController extends BaseController
{
    private $productsService;
    private $favoritesService;

    public function __construct(
        App $app,
        ProductsService $productsService,
        FavoritesService $favoritesService
    )
    {
        parent::__construct($app);
        $this->productsService = $productsService;
        $this->favoritesService = $favoritesService;
    }

    public function list() {
        $merchantId = $this->request->merchant->id;
        $languageKey = $this->request->languageKey;
        $type = $this->request->param('type', 0);
        $categoryId = $this->request->param('category_id', 0);
        $discountType = $this->request->param('discount_type', 0);
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 5);

        $list = $this->productsService->list($merchantId, $type, $categoryId, $discountType, $languageKey, $page, $count);

        if (!$list->count()) {
            return ApiResponse::returnRes([]);
        }

        $list = $list->toArray();
        $list = $list['data'];
        foreach ($list as $index => $item) {
            $item['cover'] = json_decode($item['cover'], true);
            $formatListPrice = $this->productsService->formatListPrice($item['spu_id']);
            if (!$formatListPrice) {
                unset($list[$index]);
                continue;
            }

            $list[$index] = array_merge($item, $formatListPrice);
        }

        return ApiResponse::returnRes(array_values($list));
    }

    public function detail() {
        $languageKey = $this->request->languageKey;
        $spuId = $this->request->param('spu_id', 0);

        if (!$spuId) {
            HttpEx('', 50013);
        }

        $detail = $this->productsService->detail($spuId, $languageKey);

        $detail['cover'] = json_decode($detail['cover']);

//        if (isset($detail["detail"])) $detail["detail"] = json_decode($detail["detail"]);
//        if (isset($detail["notice"])) $detail["notice"] = json_decode($detail["notice"]);
//        if (isset($detail["price_schedule"])) $detail["price_schedule"] = json_decode($detail["price_schedule"]);
//        if (isset($detail["limit"])) $detail["limit"] = json_decode($detail["limit"]);

        $detail['is_favorite'] = false;
        if ($token = $this->request->header('token') ?? $this->request->param('token')) {
            $tokenRes = Tools::decodeFrontToken($token);
            $uid = $tokenRes['uid'] ?? 0;
            $detail['is_favorite'] = $this->favoritesService->isFavorites($uid, $spuId, Favorites::TYPE_PRODUCT);
        }

        return ApiResponse::returnRes($detail);
    }

    public function categoryList() {
        $merchantId = $this->request->merchant->id;
        $languageKey = $this->request->languageKey;

        $list = $this->productsService->categoryList($merchantId, $languageKey);
        return ApiResponse::returnRes($list);
    }

    public function favorite(Request $request) {
        $uid = $request->user->id;
        $spuId = $this->request->param('spu_id', 0);

        if (!$spuId) HttpEx('', 50013);

        $spu = $this->productsService->getById($spuId);

        if (!$spu || $spu['del_flag'] == Spu::DELETED) HttpEx('', 50014);

        if ($this->favoritesService->isFavorites($uid, $spuId, Favorites::TYPE_PRODUCT)) {
            return ApiResponse::returnRes(true);
        }

        $this->productsService->favorite($uid, $spuId);

        return ApiResponse::returnRes(true);
    }

    public function cancelFavorite(Request $request) {
        $uid = $request->user->id;
        $spuId = $this->request->param('spu_id', 0);

        if (!$this->favoritesService->isFavorites($uid, $spuId, Favorites::TYPE_PRODUCT)) {
            return ApiResponse::returnRes(true);
        }

        $this->productsService->cancelFavorite($uid, $spuId);

        return ApiResponse::returnRes(true);
    }
}