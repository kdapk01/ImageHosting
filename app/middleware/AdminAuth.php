<?php
declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

class AdminAuth
{
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
