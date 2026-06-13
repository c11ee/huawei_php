<?php

namespace App\Traits;


trait TreeTrait
{
    /**
     * 递归组装无限树状结构
     * @param array $elements 扁平的数组
     * @param int $parentId 当前查找的父级ID, 顶级默认为 0
     * @return array
     */
    public function buildTree(array $elements, $parentId = 0): array
    {
        $branch = [];

        foreach ($elements as $element) {
            // 如果当前元素的 parent_id 等于我们要找的父级 ID
            if ($element['parent_id'] == $parentId) {

                // 寻找当前元素的子元素 
                $children = $this->buildTree($elements, $element['id']);

                // 如果找到了子元素, 就添加到当前元素的 children 数组中
                if (!empty($children)) {
                    $element['children'] = $children;
                } else {
                    $element['children'] = [];
                }

                // 将组装好的节点添加到结果数组中
                $branch[] = $element;
            }
        }

        // 排序, 按 sort 字段升序
        usort($branch, fn($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $branch;
    }
}
