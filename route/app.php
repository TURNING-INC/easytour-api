<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('', function () {
    //--------不需要登录--------
    //user
    Route::group('u', function () {
        Route::post('login', 'front.UserController/login');
        Route::post('getPhoneNumber', 'front.UserController/getPhoneNumber');
    });

    //merchant
    Route::group('m', function () {
        Route::get('info', 'front.MerchantController/info');
    });

    //feed
    Route::group('f', function () {
        Route::get('list', 'front.FeedController/list');
        Route::get('detail', 'front.FeedController/detail');
    });

    //reply
    Route::group('r', function () {
        Route::get('list', 'front.ReplyController/list');
    });

    //product
    Route::group('p', function () {
        Route::get('list', 'front.ProductController/list');
        Route::get('detail', 'front.ProductController/detail');
        Route::get('categoryList', 'front.ProductController/categoryList');
    });

    //order
    Route::group('o', function () {
        Route::post('payCallback', 'front.OrderController/payCallback');
        Route::post('refundCallback', 'front.OrderController/refundCallback');
    });


    //--------需要登录--------
    Route::group('', function () {
        //user
        Route::group('u', function () {
            Route::get('info', 'front.UserController/info');
            Route::get('favoriteList', 'front.UserController/favoriteList');
            Route::post('uploadAvatar', 'front.UserController/uploadAvatar');
            Route::post('upd', 'front.UserController/upd');
        });

        //feed
        Route::group('f', function () {
            Route::post('like', 'front.FeedController/like');
            Route::post('cancelLike', 'front.FeedController/cancelLike');
            Route::post('favorite', 'front.FeedController/favorite');
            Route::post('cancelFavorite', 'front.FeedController/cancelFavorite');
        });

        //product
        Route::group('p', function () {
            Route::post('favorite', 'front.ProductController/favorite');
            Route::post('cancelFavorite', 'front.ProductController/cancelFavorite');
        });

        //reply
        Route::group('r', function () {
            Route::post('add', 'front.ReplyController/add');
            Route::post('del', 'front.ReplyController/del');
            Route::post('like', 'front.ReplyController/like');
            Route::post('cancelLike', 'front.ReplyController/cancelLike');
        });

        //order
        Route::group('o', function () {
            Route::post('place', 'front.OrderController/place');
            Route::post('list', 'front.OrderController/list');
            Route::post('detail', 'front.OrderController/detail');
            Route::post('writeOff', 'front.OrderController/writeOff');
        });

    })->middleware(app\middleware\UserLogin::class);

})->middleware(app\middleware\RequiredParams::class);


//后台
Route::group('backend', function () {
    //登录
    Route::post('admin/login', 'backend.AdminController/login');

    //商家信息
    Route::group('', function () {
        Route::group('m', function () {
            Route::get('info', 'backend.MerchantController/info');
            Route::post('save', 'backend.MerchantController/save');
            Route::post('bindStaff', 'backend.MerchantController/bindStaff');
            Route::post('unbindStaff', 'backend.MerchantController/unbindStaff');
            Route::get('staffList', 'backend.MerchantController/staffList');
        });

        Route::group('u', function () {
            Route::get('list', 'backend.UserController/list');
            Route::get('info', 'backend.UserController/info');
        });

        Route::group('o', function () {
            Route::get('list', 'backend.OrderController/list');
            Route::get('detail', 'backend.OrderController/detail');
            Route::post('writeOff', 'backend.OrderController/writeOff');
            Route::post('changeStatus', 'backend.OrderController/changeStatus');
            Route::post('refund', 'backend.OrderController/refund');
        });

        Route::group('p', function () {
            Route::get('list', 'backend.ProductController/list');
            Route::get('detail', 'backend.ProductController/detail');
            Route::post('saveSpu', 'backend.ProductController/saveSpu');
            Route::post('saveSpuDetail', 'backend.ProductController/saveSpuDetail');
            Route::post('saveSku', 'backend.ProductController/saveSku');
            Route::post('saveCategory', 'backend.ProductController/saveCategory');
            Route::post('delCategory', 'backend.ProductController/delCategory');
            Route::get('categoryList', 'backend.ProductController/categoryList');
        });

        Route::group('f', function () {
            Route::get('list', 'backend.FeedController/list');
            Route::get('detail', 'backend.FeedController/detail');
            Route::post('save', 'backend.FeedController/save');
            Route::get('replyList', 'backend.FeedController/replyList');
        });

    })->middleware(app\middleware\ManageAdminLogin::class);
});