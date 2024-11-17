<?php

namespace app\lib;

use Error;
use Exception;
use ParseError;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\facade\Log;
use think\Response;
use Throwable;
use TypeError;

class ExceptionHandle extends Handle
{
    public function render($request, Throwable $e): Response
    {
        // 参数验证错误
        if ($e instanceof ValidateException) {
            return ApiResponse::returnRes(false, 0, $e->getMessage());
        }

        // 请求异常
        if ($e instanceof HttpException) {
            return ApiResponse::returnRes(false, $e->getcode(), $e->getMessage());
        }

        //总异常
        if ($e instanceof Exception) {
            Log::error("file:" . $e->getFile() . ";line:" . $e->getLine() . ";msg:" . $e->getMessage());
            return ApiResponse::returnRes(false, 0, $e->getMessage());
        }

        //错误异常
        if ($e instanceof Error) {
            Log::error("file:" . $e->getFile() . ";line:" . $e->getLine() . ";msg:" . $e->getMessage());
            return ApiResponse::returnRes(false, 0, $e->getMessage());
        }
        $request->param();
        Log::error("file:" . $e->getFile() . ";line:" . $e->getLine() . ";msg:" . $e->getMessage());
        return ApiResponse::returnRes(false, 0, '系统错误');

        //// 其他错误交给系统处理
        //return parent::render($request, $e);
    }

}