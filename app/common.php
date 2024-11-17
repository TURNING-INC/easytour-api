<?php
// 应用公共文件
/**
 * http异常抛出
 * @param string $message  提示
 * @param int $code  自定义code
 * @param int $statusCode  状态码
 * @param Exception|null $previous  异常类
 * @param array $headers  头部信息
 */
function HttpEx(
    string $message = '',
    int $code = -1,
    int $statusCode = 0,
    Exception $previous = null,
    array $headers = []
)
{
    throw new \think\exception\HttpException($statusCode, $message, $previous, $headers, $code);
}
