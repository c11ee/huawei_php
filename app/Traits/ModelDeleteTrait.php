<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait ModelDeleteTrait
{
    /**
     * 收集所有子孙 ID
     *
     * @param class-string<Model> $model
     */
    protected function collectIdsWithDescendants(
        string $model,
        array $ids,
        string $parentColumn = 'parent_id'
    ): array {

        $allIds = array_values(
            array_unique(
                array_map('intval', $ids)
            )
        );

        while (true) {

            $childIds = $model::query()
                ->whereIn($parentColumn, $allIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $newIds = array_values(
                array_diff($childIds, $allIds)
            );

            if ($newIds === []) {
                break;
            }

            $allIds = array_merge($allIds, $newIds);
        }

        return $allIds;
    }
}
