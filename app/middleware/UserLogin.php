<?php
declare (strict_types=1);

namespace app\middleware;

use app\service\front\UsersService;
use Firebase\JWT\JWT;

class UserLogin
{
    /**
     * 用户登陆中间件
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(\think\Request $request, \Closure $next)
    {
        $token = $request->header('token') ?? $request->param('token');

        if (!$token) {
            HttpEx('', 50008);
        }

        //校验token
        try {
            $jwtData = (array)JWT::decode($token, env('front.jwt_key'), ['HS512']);

            if (!$jwtData['uid']) {
                throw new \Exception('Err token');
            }

            $user = app(\app\model\Users::class)->find($jwtData['uid']);

            if ($token != $user['token']) {
                throw new \Exception('Expired token');
            }

            if (!in_array($request->pathinfo(), [
                'u/info'
            ]) && !$user['phone']) {
                HttpEx('', 50008);
            }

            if (!$user) {
                throw new \Exception('Expired token');
            } else if ($user['del_flag']) {  //被清除
                HttpEx('', 50014);
            }

            $request->user = $user;
        } catch (\Exception $e) {
            if ($e->getMessage() == 'Expired token') {
                HttpEx('', 50009);
            }

            HttpEx('', 50008);
        }

        return $next($request);
    }
}
