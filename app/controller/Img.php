<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Config as model_config;
use app\model\Images as model_images;
use think\Exception;
use think\file\UploadedFile;
use think\Response;

class Img extends BaseController
{
    /**
     * 获取图片列表
     * 支持按关键字、年份、月份筛选，并返回可访问 URL
     * @return Response
     */
    public function get_list(): Response
    {
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));
        $keyword = trim((string) $this->request->param('keyword', ''));
        $year = trim((string) $this->request->param('year', ''));
        $month = trim((string) $this->request->param('month', ''));

        $list = model_images::list([
            'keyword' => $keyword,
            'year' => $year,
            'month' => $month,
        ], $page, $pageSize);

        $list['items'] = array_map(fn(array $row): array => $this->uploadImageApiArray($row), $list['items']);

        return $this->ok($list);
    }

    /**
     * 上传图片
     * @return Response
     */
    public function upload(): Response
    {
        $maxUploadSize = trim((string) $this->configValue('upload.max_size'));
        $maxUploadSize = !empty($maxUploadSize) ? $maxUploadSize : '1M';
        $maxUploadBytes = $this->parseSizeToBytes($maxUploadSize);
        $maxUploadLabel = $this->formatUploadSize($maxUploadSize);
        ini_set('upload_max_filesize', $maxUploadSize);
        ini_set('post_max_size', $maxUploadSize);

        $contentLength = (int) $this->request->server('CONTENT_LENGTH', '0');
        if ($contentLength > 0 && $contentLength > $maxUploadBytes) {
            return $this->fail('image.file_too_large', 413, 1, [
                'size' => $maxUploadLabel,
            ]);
        }

        try {
            $file = $this->request->file('file');
        } catch (Exception $exception) {
            if (\in_array($exception->getCode(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                return $this->fail('image.file_too_large', 413, 1, [
                    'size' => $maxUploadLabel,
                ]);
            }

            return $this->fail('image.upload_failed');
        }
        if (!$file instanceof UploadedFile) {
            if ($contentLength > 0) {
                return $this->fail('image.file_too_large', 413, 1, [
                    'size' => $maxUploadLabel,
                ]);
            }

            return $this->fail('image.choose_file');
        }

        if (!$file->isValid()) {
            return $this->fail('image.upload_failed');
        }

        if ($file->getSize() > $maxUploadBytes) {
            return $this->fail('image.file_too_large', 413, 1, [
                'size' => $maxUploadLabel,
            ]);
        }

        $originalName = basename(str_replace('\\', '/', $file->getOriginalName()));
        $originalName = mb_substr($originalName !== '' ? $originalName : 'image', 0, 255);
        $tmpPath = $file->getPathname();
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (finfo_file($finfo, $tmpPath) ?: '') : '';
        if ($finfo) {
            unset($finfo);
        }
        if ($mime === 'image/svg') {
            $mime = 'image/svg+xml';
        }

        /**
         * @var array 允许上传的文件类型
         */
        $allowedMime = model_config::allowedMimes();
        if (!$allowedMime) {
            return $this->fail('image.allowed_type_not_configured', 500);
        }

        // 校验本次文件的类型
        if (!isset($allowedMime[$mime])) {
            return $this->fail('image.unsupported_type', 400, 1, [
                'types' => implode('、', array_values($allowedMime)),
            ]);
        }

        // 获取本次文件的后缀名
        $extension = $allowedMime[$mime];

        // 获取图片尺寸
        if ($extension === 'svg') {
            $imageSize = $this->readSvgSize($tmpPath);
        } else {
            $size = getimagesize($tmpPath);
            $imageSize = [
                'width' => $size ? (int) $size[0] : null,
                'height' => $size ? (int) $size[1] : null,
            ];
        }
        if ($extension !== 'svg' && (!$imageSize['width'] || !$imageSize['height'])) {
            return $this->fail('image.invalid_file');
        }
        if ($extension !== 'svg' && !$this->canProcessImage((int) $imageSize['width'], (int) $imageSize['height'])) {
            return $this->fail('image.resolution_too_large', 413);
        }

        $now = time();
        $year = date('Y', $now);
        $month = date('m', $now);

        // 生成文件uid
        $uid = substr(hash_file('sha256', $tmpPath), 0, 16);
        $targetRelativePath = $this->uploadStoragePath($year, $month, model_images::storedName($uid, $extension));
        $targetPath = $this->uploadPhysicalPath($targetRelativePath);

        $existing = model_images::findByUid($uid);
        if ($existing) {
            if ($existing['extension'] !== $extension) {
                return $this->fail('image.hash_collision', 409);
            }

            $existingPath = $this->uploadPhysicalPath($this->uploadImagePath($existing));
            if (is_file($existingPath)) {
                $this->makeThumbnail($existingPath, $existing);
                return $this->ok($this->uploadImageApiArray($existing), 'image.exists');
            }

            $targetPath = $existingPath;
            $year = (string) $existing['year'];
            $month = (string) $existing['month'];
            $targetRelativePath = $this->uploadImagePath($existing);
        }

        try {
            $this->uploadDisk()->createDirectory($this->uploadStoragePath($year, $month));
        } catch (\Throwable) {
            return $this->fail('image.create_directory_failed', 500);
        }

        $saved = $this->sanitizeAndSave($tmpPath, $targetRelativePath, $extension);
        if (!$saved) {
            return $this->fail('image.sanitize_failed');
        }

        $sizeKb = round(filesize($targetPath) / 1024, 2);
        $row = [
            'uid' => $uid,
            'original_name' => $originalName,
            'extension' => $extension,
            'mime' => $mime,
            'size_kb' => $sizeKb,
            'width' => $imageSize['width'],
            'height' => $imageSize['height'],
            'year' => $year,
            'month' => $month,
            'created_at' => $now,
        ];

        $row = model_images::createRecord($row);
        $this->makeThumbnail($targetPath, $row);

        return $this->ok($this->uploadImageApiArray($row), 'image.upload_success');
    }

    /**
     * 获取图片详情
     * @param string $id 图片ID
     * @return Response
     */
    public function read(string $id): Response
    {
        $row = model_images::findById($id);
        if (!$row) {
            return $this->fail('image.not_found', 404);
        }

        return $this->ok($this->uploadImageApiArray($row));
    }

    /**
     * 删除图片
     * 同时删除原图、缩略图和动态尺寸缓存
     * @param string $id 图片ID
     * @return Response
     */
    public function delete(string $id): Response
    {
        $row = model_images::findById($id);
        if (!$row) {
            return $this->fail('image.not_found', 404);
        }

        model_images::deleteById($id);

        $path = $this->uploadPhysicalPath($this->uploadImagePath($row));
        if (is_file($path)) {
            unlink($path);
        }

        $thumbnailPath = $this->uploadPhysicalPath($this->uploadThumbnailPath($row));
        if (is_file($thumbnailPath)) {
            unlink($thumbnailPath);
        }

        $cacheRoot = $this->uploadPhysicalPath($this->uploadStoragePath('_cache'));
        if (is_dir($cacheRoot)) {
            foreach (new \FilesystemIterator($cacheRoot, \FilesystemIterator::SKIP_DOTS) as $directory) {
                if (!$directory->isDir() || $directory->getFilename() === 'thumb') {
                    continue;
                }

                $path = $directory->getPathname()
                    . DIRECTORY_SEPARATOR
                    . model_images::storedName((string) $row['uid'], (string) $row['extension']);
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        return $this->ok([], 'image.delete_success');
    }

    /**
     * 查看图片
     * @param string $year 图片年份归档
     * @param string $month 图片月份归档
     * @param string $file 文件名
     * @param mixed $width 动态缩放宽度
     * @param mixed $height 动态缩放高度
     * @return Response
     */
    public function show(string $year, string $month, string $file, $width = null, $height = null): Response
    {
        return $this->sendImage($year, $month, $file, $width, $height);
    }

    /**
     * 查看缩略图
     * @param string $year 图片年份归档
     * @param string $month 图片月份归档
     * @param string $file 文件名
     * @return Response
     */
    public function thumb(string $year, string $month, string $file): Response
    {
        return $this->sendImage($year, $month, $file, null, null, true);
    }

    /**
     * 图片输出处理
     * @param string $year 图片年份归档
     * @param string $month 图片月份归档
     * @param string $file 文件名
     * @param mixed $width 动态缩放宽度
     * @param mixed $height 动态缩放高度
     * @param bool $thumb 是否输出缩略图
     * @return Response\File
     */
    private function sendImage(string $year, string $month, string $file, $width = null, $height = null, bool $thumb = false): Response
    {
        if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^\d{2}$/', $month)) {
            abort(404);
        }

        $name = basename($file);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $uid = pathinfo($name, PATHINFO_FILENAME);

        // 防盗链
        $this->abortHotlinkRequest($extension);

        $row = model_images::findPublic($year, $month, $uid, $extension);

        if (!$row) {
            abort(404);
        }

        $path = $this->uploadPhysicalPath($this->uploadImagePath($row));
        if (!is_file($path)) {
            abort(404);
        }

        $width = $width ? min(4096, max(1, $width)) : null;
        $height = $height ? min(4096, max(1, $height)) : null;

        if ($thumb && $row['extension'] !== 'svg') {
            $path = $this->thumbnailPath($path, $row);
        } elseif ($width && $height && $row['extension'] !== 'svg') {
            $path = $this->resizedPath($path, (string) $row['uid'], (string) $row['extension'], $width, $height);
        }

        return download($path, basename($path), false, 86400 * 30)->force(false);
    }

    /**
     * 根据HTTP_REFERER执行图片防盗链校验
     * @param string $extension 请求图片后缀
     * @return void
     */
    private function abortHotlinkRequest(string $extension): void
    {
        $config = model_config::hotlinkProtection();
        if (!$config['enabled']) {
            return;
        }

        if ($config['extensions'] && !\in_array(strtolower($extension), $config['extensions'], true)) {
            return;
        }

        $referer = trim((string) $this->request->server('HTTP_REFERER', ''));
        if ($referer === '') {
            if ($config['allow_empty_referer']) {
                return;
            }

            abort($config['deny_status']);
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            abort($config['deny_status']);
        }

        $host = $this->normalizeHost($host);
        $currentHost = $this->normalizeHost((string) $this->request->server('HTTP_HOST', ''));
        if ($currentHost !== '' && $host === $currentHost) {
            return;
        }

        foreach ($config['allowed_domains'] as $domain) {
            if ($this->domainMatches($host, $domain)) {
                return;
            }
        }

        abort($config['deny_status']);
    }

    /**
     * 判断来源域名是否命中许可域名，支持 example.com 和 *.example.com
     * @param string $host 来源域名
     * @param string $domain 许可域名
     * @return bool
     */
    private function domainMatches(string $host, string $domain): bool
    {
        $domain = $this->normalizeHost($domain);
        if ($domain === '') {
            return false;
        }

        if (str_starts_with($domain, '*.')) {
            $suffix = substr($domain, 1);

            return str_ends_with($host, $suffix) && $host !== ltrim($suffix, '.');
        }

        return $host === $domain;
    }

    /**
     * 标准化域名，去除协议和端口
     * @param string $host 域名配置或请求Host
     * @return string
     */
    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);

            return \is_string($parsed) ? strtolower($parsed) : '';
        }

        $host = preg_replace('/:\d+$/', '', $host) ?: '';

        return trim($host, '.');
    }

    /**
     * 获取SVG文件尺寸
     * @param string $path SVG临时文件路径
     * @return array{height: int|null, width: int|null}
     */
    private function readSvgSize(string $path): array
    {
        $content = file_get_contents($path, false, null, 0, 4096) ?: '';
        $width = null;
        $height = null;

        if (preg_match('/\bwidth=["\']?([0-9.]+)/i', $content, $matches)) {
            $width = (int) round((float) $matches[1]);
        }
        if (preg_match('/\bheight=["\']?([0-9.]+)/i', $content, $matches)) {
            $height = (int) round((float) $matches[1]);
        }
        if ((!$width || !$height) && preg_match('/\bviewBox=["\'][^"\']*\s+([0-9.]+)\s+([0-9.]+)["\']/i', $content, $matches)) {
            $width = $width ?: (int) round((float) $matches[1]);
            $height = $height ?: (int) round((float) $matches[2]);
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * 净化文件并存储
     * SVG执行文本安全检查，位图使用GD重新编码后写入存储磁盘
     * @param string $source 上传临时文件路径
     * @param string $target 存储磁盘内的目标路径
     * @param string $extension 目标扩展名
     * @return bool
     */
    private function sanitizeAndSave(string $source, string $target, string $extension): bool
    {
        // 单独存储SVG文件
        if ($extension === 'svg') {
            $content = file_get_contents($source);
            if ($content === false || stripos($content, '<svg') === false) {
                return false;
            }

            $dangerous = '/<script\b|on[a-z]+\s*=|javascript:|data:text\/html|<foreignObject\b|<iframe\b|<object\b|<embed\b/i';
            if (preg_match($dangerous, $content)) {
                return false;
            }

            return $this->uploadWrite($target, $content);
        }

        // 通用存储
        $image = match ($extension) {
            'jpg' => imagecreatefromjpeg($source),
            'png' => imagecreatefrompng($source),
            'gif' => imagecreatefromgif($source),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false,
            default => false,
        };

        if (!$image) {
            return false;
        }

        imagesavealpha($image, true);

        ob_start();
        $result = match ($extension) {
            'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image, null, 6),
            'gif' => imagegif($image),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, 90) : false,
            default => false,
        };
        $content = ob_get_clean();
        unset($image);

        return $result && is_string($content) && $this->uploadWrite($target, $content);
    }

    /**
     * 将PHP容量配置值转换为字节数
     * @param string $size 容量配置值
     * @return int
     */
    private function parseSizeToBytes(string $size): int
    {
        $size = trim($size);
        if ($size === '') {
            return 1024 * 1024;
        }

        if (is_numeric($size)) {
            return max(1, (int) $size);
        }

        if (!preg_match('/^\s*([0-9.]+)\s*([kmgt]?b?|)\s*$/i', $size, $matches)) {
            return 1024 * 1024;
        }

        $value = (float) $matches[1];
        $unit = strtolower($matches[2] ?: 'b');
        $bytes = match ($unit) {
            'g', 'gb' => $value * 1024 * 1024 * 1024,
            'm', 'mb' => $value * 1024 * 1024,
            'k', 'kb' => $value * 1024,
            default => $value,
        };

        return max(1, (int) round($bytes));
    }

    /**
     * 格式化上传大小配置值
     * @param string $size 上传大小配置值
     * @return string
     */
    private function formatUploadSize(string $size): string
    {
        $size = trim($size);
        if ($size === '') {
            return '1M';
        }

        if (preg_match('/^\d+(?:\.\d+)?\s*[kmgt]?b?$/i', $size)) {
            return strtoupper(str_replace(' ', '', $size));
        }

        return '1M';
    }

    /**
     * 判断当前内存限制下是否适合使用GD处理图片
     * @param int $width 图片宽度
     * @param int $height 图片高度
     * @return bool
     */
    private function canProcessImage(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            return false;
        }

        $memoryLimit = trim((string) ini_get('memory_limit'));
        if ($memoryLimit === '-1') {
            return true;
        }

        $limit = $this->parseSizeToBytes($memoryLimit);
        if ($limit < 1) {
            return true;
        }

        $available = $limit - memory_get_usage(true);
        $reserved = 16 * 1024 * 1024;
        if ($available <= $reserved) {
            return false;
        }

        $estimated = $width * $height * 8;

        return $estimated < ($available - $reserved);
    }

    /**
     * 获取或生成动态尺寸图片路径
     * @param string $path 原图物理路径
     * @param string $uid 图片UID
     * @param string $extension 图片扩展名
     * @param int $width 目标宽度
     * @param int $height 目标高度
     * @return string 缓存图片物理路径，生成失败时返回原图路径
     */
    private function resizedPath(string $path, string $uid, string $extension, int $width, int $height): string
    {
        if (!$this->canProcessImage($width, $height)) {
            return $path;
        }

        $cachePath = $this->uploadPhysicalPath(
            $this->uploadStoragePath('_cache', $width . 'x' . $height, $uid . '.' . $extension)
        );
        $cacheDir = dirname($cachePath);

        if (is_file($cachePath)) {
            return $cachePath;
        }

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return $path;
        }

        $size = getimagesize($path);
        if (!$size || !$this->canProcessImage((int) $size[0], (int) $size[1])) {
            return $path;
        }

        $source = match ($extension) {
            'jpg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$source) {
            return $path;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        match ($extension) {
            'jpg' => imagejpeg($canvas, $cachePath, 90),
            'png' => imagepng($canvas, $cachePath, 6),
            'gif' => imagegif($canvas, $cachePath),
            'webp' => function_exists('imagewebp') ? imagewebp($canvas, $cachePath, 90) : false,
            default => false,
        };

        return is_file($cachePath) ? $cachePath : $path;
    }

    /**
     * 获取或生成缩略图路径
     * @param string $path 原图物理路径
     * @param array $row 图片记录
     * @return string 缩略图物理路径，生成失败时返回原图路径
     */
    private function thumbnailPath(string $path, array $row): string
    {
        $extension = (string) $row['extension'];
        $cachePath = $this->uploadPhysicalPath($this->uploadThumbnailPath($row));
        $cacheDir = dirname($cachePath);

        if (is_file($cachePath)) {
            return $cachePath;
        }

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return $path;
        }

        $size = getimagesize($path);
        if (!$size || !$this->canProcessImage((int) $size[0], (int) $size[1])) {
            return $path;
        }

        $source = match ($extension) {
            'jpg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'gif' => imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$source) {
            return $path;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return $path;
        }

        $scale = min(
            model_images::THUMB_MAX_WIDTH / $sourceWidth,
            model_images::THUMB_MAX_HEIGHT / $sourceHeight,
            1
        );
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        match ($extension) {
            'jpg' => imagejpeg($canvas, $cachePath, 85),
            'png' => imagepng($canvas, $cachePath, 6),
            'gif' => imagegif($canvas, $cachePath),
            'webp' => function_exists('imagewebp') ? imagewebp($canvas, $cachePath, 85) : false,
            default => false,
        };

        return is_file($cachePath) ? $cachePath : $path;
    }

    /**
     * 创建缩略图
     * @param string $path 原图物理路径
     * @param array $row 图片记录
     * @return void
     */
    private function makeThumbnail(string $path, array $row): void
    {
        if ($row['extension'] === 'svg') {
            // SVG没有缩略图
            return;
        }

        $this->thumbnailPath($path, $row);
    }

}
