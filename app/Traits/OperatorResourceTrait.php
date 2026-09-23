<?php

namespace App\Traits;

use App\Http\Resources\UserResource;
use Illuminate\Support\Collection;

trait OperatorResourceTrait
{
    /**
     * 将模型（或集合）转为数组，并把操作人关联包装成 UserResource
     *
     * @param  iterable  $items
     * @param  array<int, string>  $relations
     */
    protected function withOperatorResources($items, array $relations = ['creator', 'updater']): Collection
    {
        return collect($items)->map(function ($item) use ($relations) {
            $arr = $item->toArray();

            foreach ($relations as $relation) {
                $arr[$relation] = UserResource::nullable($item->{$relation});
            }

            return $arr;
        });
    }
}
