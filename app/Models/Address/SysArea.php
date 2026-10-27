<?php

namespace App\Models\Address;

use App\Models\BaseModel;

class SysArea extends BaseModel
{
    protected $table = 'sys_area';

    protected $guarded = [];

    /**
     * 使用 ext_name 查询行政区划
     *
     * ext_name 存在跨省市的同名情况（如朝阳区），传入 parent_id 可限定范围避免命中错误数据
     */
    public static function findAreaByExtName(string $extName, ?int $parentId = null): ?SysArea
    {
        return SysArea::query()
            ->where('ext_name', $extName)
            ->where('status', 1)
            // parent_id 为 null 时不加限定，为 0 时表示查省级
            ->when($parentId !== null, fn($query) => $query->where('parent_id', $parentId))
            ->first(['id', 'parent_id', 'name']);
    }

    /**
     * 按高德编码查询行政区划
     */
    public static function findArea(string|int|null $code): ?SysArea
    {
        $code = (string) $code;

        // 空编码或顶级 parent_id = 0（省再往上没有节点）直接跳过查询
        if ($code === '' || $code === '0') {
            return null;
        }

        return SysArea::query()
            // ext_id 为 12 位标准编码，短编码在右侧补 0 对齐
            ->where('ext_id', str_pad($code, 12, '0'))
            ->where('status', 1)
            ->first(['id', 'parent_id', 'name']);
    }

    /**
     * 按主键 id 查询行政区划（用于 parent_id 逐级上溯）
     */
    public static function findAreaById(?int $id): ?SysArea
    {
        if (! $id) {
            return null;
        }

        return SysArea::query()
            ->where('id', $id)
            ->where('status', 1)
            ->first(['id', 'parent_id', 'name']);
    }
}
