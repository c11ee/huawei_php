<?php

namespace App\Http\Controllers;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * 序列化日期
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function success($data = [], $msg = 'ok')
    {
        return response()->json([
            'code' => 200,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    public static function error($msg = 'error', $code = 0, $data = [])
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 收集待删 ID 及其所有子孙节点 ID（去重）
     *
     * @param  class-string<Model>  $model
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    protected function collectIdsWithDescendants(string $model, array $ids, string $parentColumn = 'parent_id'): array
    {
        $allIds = array_values(array_unique(array_map('intval', $ids)));

        while (true) {
            $childIds = $model::query()
                ->whereIn($parentColumn, $allIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $newIds = array_values(array_diff($childIds, $allIds));
            if ($newIds === []) {
                break;
            }
            $allIds = array_merge($allIds, $newIds);
        }

        return $allIds;
    }

    /**
     * 将扁平数组转为树形结构（仅增加 children 字段）
     *
     * @param  array<int, array<string, mixed>>  $data  须包含 id、parent_id
     * @return array<int, array<string, mixed>>
     */
    protected function handleTreeData(array $data, string $parentKey = 'parent_id'): array
    {
        if ($data === []) {
            return [];
        }

        $items = [];
        foreach ($data as $row) {
            $row = (array) $row;
            $row['children'] = [];
            $items[$row['id']] = $row;
        }

        $tree = [];
        foreach ($items as &$item) {
            $parentId = $item[$parentKey] ?? 0;
            if ($parentId && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
