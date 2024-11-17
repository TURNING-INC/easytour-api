<?php
declare (strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use app\lib\ApiResponse;

class ManageAdminLogin
{
    /**
     * 管理后台-管理员登陆中间件
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(\think\Request $request, \Closure $next)
    {
        $token =  $request->header('token') ?? $request->param('token');

        if (!$token) {
            HttpEx('', 50008);
        }

        //校验token
        try {
            $jwtData = (array)JWT::decode($token, env('backend.jwt_key'), ['HS512']);

            if (!$jwtData['admin_id']) {
                throw new \Exception('Err token');
            }

            $admin = app(\app\model\Admins::class)->getById($jwtData['admin_id']);

            if (!$admin) {
                throw new \Exception('Expired token');
            }

            if (!$admin->merchant_id) {
                throw new \Exception('Need merchant id');
            }

            $request->admin = $admin;
        } catch (\Exception $e) {var_dump($e->getMessage());
            if ($e->getMessage() == 'Expired token') {
                HttpEx('', 50014);
            }

            if ($e->getMessage() == 'Need merchant id') {
                HttpEx('还未绑定商家');
            }

            HttpEx('', 50008);
        }

        return $next($request);
    }
}
