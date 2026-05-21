<?php

namespace App\Traits;


trait TreeTrait
{
    /**
     * 将扁平数组转为树形结构（仅增加 children 字段）
     *
     * @param  array<int, array<string, mixed>>  $data  须包含 id、parent_id
     * @return array<int, array<string, mixed>>
     */
    public function handleTreeData(array $data, string $parentKey = 'parent_id'): array
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
