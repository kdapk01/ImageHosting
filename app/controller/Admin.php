<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\Exception;
use think\file\UploadedFile;
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

    /**
     * 获取站点设置
     * @return Response
     */
    public function settings(): Response
    {
        return $this->ok($this->settingsData());
    }

    /**
     * 更新站点设置
     * @return Response
     */
    public function updateSettings(): Response
    {
        $title = trim((string) $this->request->post('site_title', ''));
        if ($title === '') {
            return $this->fail('settings.title_required');
        }

        $title = mb_substr($title, 0, 80);

        $allowedMimes = trim((string) $this->request->post('upload_allowed_mimes', ''));
        $decodedMimes = json_decode($allowedMimes, true);
        if (!\is_array($decodedMimes) || $decodedMimes === []) {
            return $this->fail('settings.allowed_mimes_invalid');
        }
        foreach ($decodedMimes as $mime => $extension) {
            if (
                !\is_string($mime)
                || !\is_string($extension)
                || !preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mime)
                || !preg_match('/^[a-z0-9]+$/i', $extension)
            ) {
                return $this->fail('settings.allowed_mimes_invalid');
            }
        }

        $maxSize = strtoupper(str_replace(' ', '', trim((string) $this->request->post('upload_max_size', '1M'))));
        if (!preg_match('/^\d+(?:\.\d+)?[KMGT]?B?$/i', $maxSize)) {
            return $this->fail('settings.upload_size_invalid');
        }

        $disk = trim((string) $this->request->post('upload_disk', 'upload'));
        if ($disk === '' || !preg_match('/^[a-z0-9_-]+$/i', $disk)) {
            return $this->fail('settings.upload_disk_invalid');
        }

        $uploadPath = $this->normalizeStoragePath((string) $this->request->post('upload_path', ''));
        $hotlinkEnabled = $this->boolInput('hotlink_enabled') ? '1' : '0';
        $allowEmptyReferer = $this->boolInput('hotlink_allow_empty_referer') ? '1' : '0';
        $extensions = preg_split('/[\r\n,]+/', (string) $this->request->post('hotlink_extensions', '')) ?: [];
        $extensions = array_map(
            static fn(string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $extensions
        );
        $extensions = implode(',', array_values(array_unique(array_filter(
            $extensions,
            static fn(string $extension): bool => $extension !== '' && preg_match('/^[a-z0-9]+$/i', $extension) === 1
        ))));

        $allowedDomains = preg_split('/[\r\n,]+/', (string) $this->request->post('hotlink_allowed_domains', '')) ?: [];
        $allowedDomains = array_map(static fn(string $domain): string => strtolower(trim($domain)), $allowedDomains);
        $allowedDomains = implode(',', array_values(array_unique(array_filter(
            $allowedDomains,
            static fn(string $domain): bool => $domain !== ''
        ))));

        $denyStatus = (int) $this->request->post('hotlink_deny_status', 403);
        if ($denyStatus < 400 || $denyStatus > 599) {
            return $this->fail('settings.deny_status_invalid');
        }

        model_config::setValues([
            'site.title' => $title,
            'upload.allowed_mimes' => $allowedMimes,
            'upload.max_size' => $maxSize,
            'upload.disk' => $disk,
            'upload.path' => $uploadPath,
            'hotlink.enabled' => $hotlinkEnabled,
            'hotlink.allow_empty_referer' => $allowEmptyReferer,
            'hotlink.extensions' => $extensions,
            'hotlink.allowed_domains' => $allowedDomains,
            'hotlink.deny_status' => (string) $denyStatus,
        ]);

        return $this->ok($this->settingsData(), 'settings.saved');
    }

    /**
     * 上传并替换 favicon.ico
     * @return Response
     */
    public function uploadFavicon(): Response
    {
        try {
            $file = $this->request->file('file');
        } catch (Exception) {
            return $this->fail('settings.favicon_upload_failed');
        }

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->fail('settings.favicon_required');
        }

        if ($file->getSize() > 1024 * 512) {
            return $this->fail('settings.favicon_too_large', 413);
        }

        $extension = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        $handle = fopen($file->getPathname(), 'rb');
        $header = $handle ? fread($handle, 6) : false;
        if ($handle) {
            fclose($handle);
        }
        $bytes = \is_string($header) && strlen($header) >= 6
            ? unpack('vreserved/vtype/vcount', $header)
            : false;
        if (
            $extension !== 'ico'
            || !\is_array($bytes)
            || $bytes['reserved'] !== 0
            || $bytes['type'] !== 1
            || $bytes['count'] <= 0
        ) {
            return $this->fail('settings.favicon_invalid');
        }

        $target = $this->app->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'favicon.ico';
        if (!copy($file->getPathname(), $target)) {
            return $this->fail('settings.favicon_upload_failed', 500);
        }

        return $this->ok([
            'favicon_url' => $this->faviconUrl(),
        ], 'settings.favicon_saved');
    }

    /**
     * 获取设置页面需要的配置数据
     * @return array
     */
    private function settingsData(): array
    {
        $title = trim((string) model_config::valueOf('site.title', 'ImageHosting'));

        return [
            'site_title' => $title !== '' ? $title : 'ImageHosting',
            'favicon_url' => $this->faviconUrl(),
            'upload_allowed_mimes' => (string) model_config::valueOf('upload.allowed_mimes', '{"image/jpeg":"jpg","image/png":"png","image/gif":"gif","image/webp":"webp","image/svg+xml":"svg"}'),
            'upload_max_size' => (string) model_config::valueOf('upload.max_size', '1M'),
            'upload_disk' => (string) model_config::valueOf('upload.disk', 'upload'),
            'upload_path' => (string) model_config::valueOf('upload.path', ''),
            'hotlink_enabled' => $this->configBool('hotlink.enabled', false),
            'hotlink_allow_empty_referer' => $this->configBool('hotlink.allow_empty_referer', true),
            'hotlink_extensions' => (string) model_config::valueOf('hotlink.extensions', 'jpg,jpeg,png,gif,webp,svg'),
            'hotlink_allowed_domains' => (string) model_config::valueOf('hotlink.allowed_domains', ''),
            'hotlink_deny_status' => (int) model_config::valueOf('hotlink.deny_status', '403'),
        ];
    }

    /**
     * 获取 favicon 访问地址并附带缓存版本
     * @return string
     */
    private function faviconUrl(): string
    {
        $path = $this->app->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'favicon.ico';
        $version = is_file($path) ? (string) filemtime($path) : (string) time();

        return "/favicon.ico?v=$version";
    }

    /**
     * 读取布尔型表单输入
     * @param string $key 字段名
     * @return bool
     */
    private function boolInput(string $key): bool
    {
        $value = $this->request->post($key, false);

        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true'
            || $value === 'on';
    }

    /**
     * 读取布尔型配置值
     * @param string $key 配置键
     * @param bool $default 默认值
     * @return bool
     */
    private function configBool(string $key, bool $default): bool
    {
        $value = strtolower(trim((string) model_config::valueOf($key, $default ? '1' : '0')));

        return \in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
