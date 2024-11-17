<?php

namespace app\middleware;
use app\lib\ApiResponse;

class RequiredParams
{
    /**
     * 校验必传参数
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(\think\Request $request, \Closure $next)
    {
        $merchantId =  $request->header('merchant_id') ?? $request->param('merchant_id');
        $mp =  $request->header('mp') ?? $request->param('mp');
        $language =  $request->header('language') ?? $request->param('language');
        $language = $language ?: 'zh-cn' ;
        $languageKey = strtolower(str_replace('-', '_', $language));

        if (!$merchantId) {
            HttpEx('', 50010);
        }

        if (!$mp) {
            HttpEx('', 50012);
        }

        try {
            $merchant = app(\app\model\Merchants::class)->getByEncodeId($merchantId, $languageKey);

            if (!$merchant) {
                HttpEx('', 50011);
            }

            $request->merchant = $merchant;
            $request->mp = $mp;
            $request->language = $language;
            $request->languageKey = $languageKey;
        } catch (\Exception $e) {
            HttpEx('', 50011);
        }

        return $next($request);
    }
}