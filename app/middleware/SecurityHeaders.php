<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 安全标头
 */
class SecurityHeaders
{
    /**
     * 给响应添加安全标头
     * @param Request $request 当前请求
     * @param Closure $next 后续处理器
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->header([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);

        return $response;
    }
}
