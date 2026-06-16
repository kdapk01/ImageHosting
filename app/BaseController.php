<?php
declare(strict_types=1);

namespace app;

use app\model\Config as model_config;
use think\App;
use think\facade\Filesystem;
use think\filesystem\Driver;
use think\Response;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 控制器初始化钩子
     * @return void
     */
    protected function initialize()
    {
    }

    /**
     * 返回成功 JSON 响应
     * @param array $data 响应数据
     * @param string $message 语言包消息键
     * @param array $vars 语言包变量
     * @return Response
     */
    protected function ok(array $data = [], string $message = 'ok', array $vars = []): Response
    {
        return json([
            'code' => 0,
            'message' => $this->trans($message, $vars),
            'data' => $data,
        ]);
    }

    /**
     * 返回失败 JSON 响应
     * @param string $message 语言包消息键
     * @param int $status HTTP 状态码
     * @param int $code 业务错误码
     * @param array $vars 语言包变量
     * @return Response
     */
    protected function fail(string $message, int $status = 400, int $code = 1, array $vars = []): Response
    {
        return json([
            'code' => $code,
            'message' => $this->trans($message, $vars),
            'data' => null,
        ], $status);
    }

    /**
     * 读取配置值，异常时返回默认值
     * @param string $key 配置键
     * @param string|null $default 默认值
     * @return string|null
     */
    protected function configValue(string $key, ?string $default = null): ?string
    {
        try {
            $value = model_config::valueOf($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return $value;
    }

    /**
     * 获取上传文件系统磁盘
     * @return Driver
     */
    protected function uploadDisk(): Driver
    {
        $disk = trim((string) $this->configValue('upload.disk', ''));

        return Filesystem::disk($disk !== '' ? $disk : 'upload');
    }

    /**
     * 拼接上传存储相对路径
     * @param string ...$segments 路径片段
     * @return string
     */
    protected function uploadStoragePath(string ...$segments): string
    {
        $paths = [];
        $prefix = $this->normalizeStoragePath((string) $this->configValue('upload.path', ''));
        if ($prefix !== '') {
            $paths[] = $prefix;
        }

        foreach ($segments as $segment) {
            $path = $this->normalizeStoragePath($segment);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return implode('/', $paths);
    }

    /**
     * 获取上传文件物理路径
     * @param string $path 存储相对路径
     * @return string
     */
    protected function uploadPhysicalPath(string $path): string
    {
        return $this->uploadDisk()->path($path);
    }

    /**
     * 写入上传文件内容
     * @param string $path 存储相对路径
     * @param string $content 文件内容
     * @return bool
     */
    protected function uploadWrite(string $path, string $content): bool
    {
        try {
            $this->uploadDisk()->write($path, $content);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * 生成上传图片文件名
     * @param string $uid 图片 UID
     * @param string $extension 图片扩展名
     * @return string
     */
    protected function uploadImageName(string $uid, string $extension): string
    {
        return $uid . '.' . $extension;
    }

    /**
     * 获取图片原图存储路径
     * @param array $row 图片记录
     * @return string
     */
    protected function uploadImagePath(array $row): string
    {
        return $this->uploadStoragePath(
            (string) $row['year'],
            (string) $row['month'],
            $this->uploadImageName((string) $row['uid'], (string) $row['extension'])
        );
    }

    /**
     * 获取图片缩略图存储路径
     * @param array $row 图片记录
     * @return string
     */
    protected function uploadThumbnailPath(array $row): string
    {
        return $this->uploadStoragePath(
            (string) $row['year'],
            (string) $row['month'],
            (string) $row['uid'] . '_thumb.' . (string) $row['extension']
        );
    }

    /**
     * 转换图片记录为 API 响应数组
     * @param array $row 图片记录
     * @return array
     */
    protected function uploadImageApiArray(array $row): array
    {
        $name = $this->uploadImageName((string) $row['uid'], (string) $row['extension']);
        $path = $this->uploadImagePath($row);
        try {
            $url = $this->uploadDisk()->url($path);
        } catch (\Throwable) {
            $url = '/' . ltrim($path, '/');
        }

        return [
            'id' => (int) $row['id'],
            'uid' => $row['uid'],
            'original_name' => $row['original_name'],
            'name' => $name,
            'extension' => $row['extension'],
            'mime' => $row['mime'],
            'size_kb' => (float) $row['size_kb'],
            'width' => $row['width'] ? (int) $row['width'] : null,
            'height' => $row['height'] ? (int) $row['height'] : null,
            'year' => $row['year'],
            'month' => $row['month'],
            'url' => $url,
            'created_at' => is_numeric($row['created_at'])
                ? date('Y-m-d H:i:s', (int) $row['created_at'])
                : (string) $row['created_at'],
        ];
    }

    /**
     * 标准化存储路径，移除空片段和危险片段
     * @param string $path 原始路径
     * @return string
     */
    protected function normalizeStoragePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = array_filter(
            explode('/', $path),
            static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'
        );

        return implode('/', $parts);
    }

    /**
     * 翻译语言包键
     * @param string $name 语言包键
     * @param array $vars 语言包变量
     * @return string
     */
    protected function trans(string $name, array $vars = []): string
    {
        $value = lang($name, $vars);

        return \is_string($value) ? $value : $name;
    }

}
