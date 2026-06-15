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

    // 初始化
    protected function initialize()
    {
    }

    protected function ok(array $data = [], string $message = 'ok', array $vars = []): Response
    {
        return json([
            'code' => 0,
            'message' => $this->trans($message, $vars),
            'data' => $data,
        ]);
    }

    protected function fail(string $message, int $status = 400, int $code = 1, array $vars = []): Response
    {
        return json([
            'code' => $code,
            'message' => $this->trans($message, $vars),
            'data' => null,
        ], $status);
    }

    protected function configValue(string $key, ?string $default = null): ?string
    {
        try {
            $value = model_config::valueOf($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return $value;
    }

    protected function uploadDisk(): Driver
    {
        $disk = trim((string) $this->configValue('upload.disk', ''));

        return Filesystem::disk($disk !== '' ? $disk : 'upload');
    }

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

    protected function uploadPhysicalPath(string $path): string
    {
        return $this->uploadDisk()->path($path);
    }

    protected function uploadWrite(string $path, string $content): bool
    {
        try {
            $this->uploadDisk()->write($path, $content);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    protected function uploadImageName(string $uid, string $extension): string
    {
        return $uid . '.' . $extension;
    }

    protected function uploadImagePath(array $row): string
    {
        return $this->uploadStoragePath(
            (string) $row['year'],
            (string) $row['month'],
            $this->uploadImageName((string) $row['uid'], (string) $row['extension'])
        );
    }

    protected function uploadThumbnailPath(array $row): string
    {
        return $this->uploadStoragePath(
            (string) $row['year'],
            (string) $row['month'],
            (string) $row['uid'] . '_thumb.' . (string) $row['extension']
        );
    }

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

    protected function normalizeStoragePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = array_filter(
            explode('/', $path),
            static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'
        );

        return implode('/', $parts);
    }

    protected function trans(string $name, array $vars = []): string
    {
        $value = lang($name, $vars);

        return \is_string($value) ? $value : $name;
    }

}
