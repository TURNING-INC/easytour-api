<?php

namespace app\controller\front;

use app\BaseController;
use app\lib\ApiResponse;
use app\lib\Wx\Utils;
use app\model\Favorites;
use app\service\front\FavoritesService;
use app\service\front\FeedsService;
use app\service\front\ProductsService;
use app\service\front\UsersService;
use think\App;
use think\Request;

class UserController extends BaseController
{
    private $usersService;
    private $favoritesService;
    private $feedsService;
    private $productsService;
    private $wxUtils;

    public function __construct(
        App $app,
        UsersService $usersService,
        FavoritesService $favoritesService,
        FeedsService $feedsService,
        ProductsService $productsService,
        Utils $wxUtils
    )
    {
        parent::__construct($app);
        $this->usersService = $usersService;
        $this->favoritesService = $favoritesService;
        $this->feedsService = $feedsService;
        $this->productsService = $productsService;
        $this->wxUtils = $wxUtils;
    }

    public function login()
    {
        $code = $this->request->param('code');
        $mp = $this->request->mp;
        $merchantId = $this->request->merchant->id;

        if (!$code) {
            HttpEx('', 50015);
        }

        $res = $this->usersService->login($code, $mp, $merchantId);

        return ApiResponse::returnRes($res);
    }

    public function getPhoneNumber()
    {
        $code = $this->request->param('code');

        if (!$code) {
            HttpEx('', 50015);
        }

        $phoneInfo = '12345678'; //$this->wxUtils->getWxPhoneInfo($code); todo

        return ApiResponse::returnRes([
            'phone_number' => $phoneInfo['phoneNumber'],
        ]);
    }

    public function info(Request $request) {
        $user = $request->user;
        $user = [
            'username' => $user['username'],
            'avatar_url' => $user['avatar_url'],
            'type' => $user['type'],
        ];
        return ApiResponse::returnRes($user);
    }

    public function upd()
    {
        $res = $this->usersService->upd($this->request->user->id, $this->request->param());

        return ApiResponse::returnRes($res);
    }

    public function uploadAvatar(FileService $fileService)
    {
        $file = $_FILES['avatar'] ?? [];

        if (!$file || !is_uploaded_file($file['tmp_name'])) {
            HttpEx('请上传头像');
        }

        $res = $fileService->uploadOss($file, 'user_avatar_dir');

        return ApiResponse::returnRes([
            'avatar_url' => $res
        ]);
    }

    public function favoriteList(Request $request) {
        $uid = $request->user->id;
        $languageKey = $request->languageKey;
        $page = $this->request->param('page', 1);
        $count = $this->request->param('count', 5);

        $list = $this->favoritesService->list($uid, $page, $count);

        foreach ($list as &$item) {
            if ($item['target_type'] == Favorites::TYPE_FEED) {
                $field = "id, `{$languageKey}_title` as title, type, cover_url";
                $feed = $this->feedsService->getById($item['target_id'], $field);
                $feed->content = '';
                $content = $this->feedsService->getContent($item['target_id'], "`{$languageKey}_content` as content");
                $content = json_decode($content['content'], true);

                foreach ($content as $data) {
                    if (isset($data['type']) && $data['type'] == 'text') {
                        $feed->content = $data['value'];
                        break;
                    }
                }
                $item->feed = $feed;
            } else { //product
                $field = "id, `{$languageKey}_name` as name, type, cover";
                $product = $this->productsService->getById($item['target_id'], $field);
                $product = $product->toArray();
                $product['cover'] = json_decode($product['cover']);

                $formatListPrice = $this->productsService->formatListPrice($item['target_id']);
                $item->product = array_merge($product, $formatListPrice);
            }
        }

        return ApiResponse::returnRes($list->items());
    }
}