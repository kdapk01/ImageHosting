<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Images extends Model
{
    public const THUMB_MAX_WIDTH = 240;
    public const THUMB_MAX_HEIGHT = 240;

    protected $name = 'images';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    /**
     * 分页查询图片列表
     * @param array{keyword?: string, year?: string, month?: string} $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array{items: array<int, array>, total: int, page: int, page_size: int}
     */
    public static function list(array $filters, int $page, int $pageSize): array
    {
        $query = (new self())->db();

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $year = trim((string) ($filters['year'] ?? ''));
        $month = trim((string) ($filters['month'] ?? ''));

        if ($keyword !== '') {
            $query->whereLike('original_name|uid', '%' . $keyword . '%');
        }
        if (preg_match('/^\d{4}$/', $year)) {
            $query->where('year', $year);
        }
        if (preg_match('/^\d{2}$/', $month)) {
            $query->where('month', $month);
        }

        $total = (clone $query)->count();
        $items = $query
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 根据ID查找图片
     * @param int|string $id 图片ID
     * @return array|null
     */
    public static function findById(int|string $id): ?array
    {
        $row = self::where('id', $id)->find();

        return $row ? $row->toArray() : null;
    }

    /**
     * 根据UID查找图片
     * @param string $uid 图片UID
     * @return array|null
     */
    public static function findByUid(string $uid): ?array
    {
        $row = self::where('uid', $uid)->find();

        return $row ? $row->toArray() : null;
    }

    /**
     * 根据公开访问参数查找图片
     * @param string $year 图片年份归档
     * @param string $month 图片月份归档
     * @param string $uid 图片UID
     * @param string $extension 图片扩展名
     * @return array|null
     */
    public static function findPublic(string $year, string $month, string $uid, string $extension): ?array
    {
        $row = self::where('year', $year)
            ->where('month', $month)
            ->where('uid', $uid)
            ->where('extension', $extension)
            ->find();

        return $row ? $row->toArray() : null;
    }

    /**
     * 创建图片记录
     * @param array $data 图片记录数据
     * @return array
     */
    public static function createRecord(array $data): array
    {
        $model = new self();
        $model->save($data);

        return $model->toArray();
    }

    /**
     * 根据ID删除图片记录
     * @param int|string $id 图片ID
     * @return bool
     */
    public static function deleteById(int|string $id): bool
    {
        return self::where('id', $id)->delete() > 0;
    }

    /**
     * 生成存储文件名
     * @param string $uid 图片UID
     * @param string $extension 图片扩展名
     * @return string
     */
    public static function storedName(string $uid, string $extension): string
    {
        return $uid . '.' . $extension;
    }
}
