<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Session;
use think\Response;
use app\model\Config as model_config;

class Admin extends BaseController
{
    /**
     * 用户登录
     * @return Response
     */
    public function login(): Response
    {
        /**
         * @var string 用户名
         */
        $username = trim((string) $this->request->post('username', ''));

        /**
         * @var string 登录密码
         */
        $password = (string) $this->request->post('password', '');

        // 校验值
        if (empty($username) or empty($password)) {
            return $this->fail('auth.username_password_required');
        }

        // 获取管理员配置
        $admin = model_config::admin();
        if (!$admin) {
            return $this->fail('auth.admin_not_configured', 500);
        }

        // 校验账号密码
        if ($username !== $admin['username'] || !password_verify($password, $admin['password_hash'])) {
            return $this->fail('auth.invalid_credentials', 401);
        }

        // 设置session
        Session::set('admin', [
            'uid' => 'admin',
            'username' => $username,
            'login_at' => time(),
        ]);

        // 返回
        return $this->ok([
            'uid' => 'admin',
            'username' => $admin['username'],
        ]);
    }

    /**
     * 获取当前登录信息(前端路由守护使用)
     * @return Response
     */
    public function me(): Response
    {
        // 获取当前请求中session的admin值
        $user = $this->request->session('admin');

        // 返回信息
        return $this->ok([
            'uid' => $user['uid'],
            'username' => $user['username'],
        ]);
    }

    /**
     * 用户登出
     * @return Response
     */
    public function logout(): Response
    {
        Session::delete('admin');

        return $this->ok();
    }
}
