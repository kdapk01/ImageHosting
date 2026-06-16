<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

class AdminAuth
{
    /**
     * 校验管理员登录态
     * @param Request $request 当前请求
     * @param Closure $next 后续处理器
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->session('admin');

        if (!\is_array($admin) || empty($admin['username'])) {
            return json([
                'code' => 401,
                'message' => lang('auth.required'),
                'data' => null,
            ], 401);
        }

        $request->admin = $admin;

        return $next($request);
    }
}
