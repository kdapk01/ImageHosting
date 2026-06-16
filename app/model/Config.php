<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Config extends Model
{
    protected $name = 'config';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    /**
     * 读取配置值
     * @param string $key 配置键
     * @param string|null $default 默认值
     * @return string|null
     */
    public static function valueOf(string $key, ?string $default = null): ?string
    {
        $value = self::where('config_key', $key)->value('config_value');

        return $value === null ? $default : (string) $value;
    }

    /**
     * 获取管理员登录配置
     * @return array{username: string, password_hash: string}|null
     */
    public static function admin(): ?array
    {
        $username = trim((string) self::valueOf('admin.username', ''));
        $passwordHash = (string) self::valueOf('admin.password_hash', '');

        if ($username === '' || $passwordHash === '') {
            return null;
        }

        return [
            'username' => $username,
            'password_hash' => $passwordHash,
        ];
    }

    /**
     * 获取允许上传的MIME与扩展名映射
     * @return array<string, string>
     */
    public static function allowedMimes(): array
    {
        $value = (string) self::valueOf('upload.allowed_mimes', '');
        $map = json_decode($value, true);

        if (!\is_array($map)) {
            return [];
        }

        $allowed = [];
        foreach ($map as $mime => $extension) {
            if (\is_string($mime) && \is_string($extension) && $mime !== '' && preg_match('/^[a-z0-9]+$/i', $extension)) {
                $allowed[strtolower($mime)] = strtolower($extension);
            }
        }

        return $allowed;
    }

    /**
     * 获取图片防盗链配置
     * @return array{enabled: bool, allow_empty_referer: bool, extensions: array<int, string>, allowed_domains: array<int, string>, deny_status: int}
     */
    public static function hotlinkProtection(): array
    {
        $enabled = self::boolValue('hotlink.enabled', false);
        $allowEmptyReferer = self::boolValue('hotlink.allow_empty_referer', true);
        $extensions = self::listValue('hotlink.extensions', []);
        $allowedDomains = self::listValue('hotlink.allowed_domains', []);
        $denyStatus = (int) self::valueOf('hotlink.deny_status', '403');

        if ($denyStatus < 400 || $denyStatus > 599) {
            $denyStatus = 403;
        }

        $extensions = array_values(array_unique(array_filter(array_map(
            static fn(string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $extensions
        ), static fn(string $extension): bool => $extension !== '' && preg_match('/^[a-z0-9]+$/i', $extension) === 1)));

        $allowedDomains = array_values(array_unique(array_filter(array_map(
            static fn(string $domain): string => strtolower(trim($domain)),
            $allowedDomains
        ), static fn(string $domain): bool => $domain !== '')));

        return [
            'enabled' => $enabled,
            'allow_empty_referer' => $allowEmptyReferer,
            'extensions' => $extensions,
            'allowed_domains' => $allowedDomains,
            'deny_status' => $denyStatus,
        ];
    }

    /**
     * 读取布尔配置值
     * @param string $key 配置键
     * @param bool $default 默认值
     * @return bool
     */
    private static function boolValue(string $key, bool $default): bool
    {
        $value = self::valueOf($key, $default ? '1' : '0');
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if (\in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (\in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * 读取列表配置值，支持 JSON 数组或逗号/换行分隔
     * @param string $key 配置键
     * @param array<int, string> $default 默认值
     * @return array<int, string>
     */
    private static function listValue(string $key, array $default = []): array
    {
        $value = trim((string) self::valueOf($key, ''));
        if ($value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        if (\is_array($decoded)) {
            return array_values(array_filter($decoded, static fn($item): bool => \is_string($item)));
        }

        return array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            preg_split('/[\r\n,]+/', $value) ?: []
        ), static fn(string $item): bool => $item !== ''));
    }
}
