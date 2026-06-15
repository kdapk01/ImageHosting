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
}
