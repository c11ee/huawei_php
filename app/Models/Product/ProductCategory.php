<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCategory extends BaseModel
{
    protected $table = 'product_category';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_name',
        'parent_id',
        'sort',
        'status',
        'is_home',
        'icon',
    ];

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductSpu::class,
            'product_spu_category',
            'category_id',
            'spu_id'
        )->select([
            'product_spu.id',
            'product_spu.default_sku_id',
            'product_spu.product_short_name',
            'product_spu.default_sku_image_url',
        ])->orderBy('sort');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->with('children', 'goods');
    }

    /**
     * 给定一批分类 id，返回每个分类「自身 + 所有子孙分类」的 id 集合
     *
     * @param  array<int, int|string>  $categoryIds
     * @return array<int, array<int>>
     */
    public static function subtreeIds(array $categoryIds): array
    {
        // 「父分类 -> 子分类 id」映射（取全部分类，避免中间层被禁用导致断链）
        $childrenMap = static::query()
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn($rows) => $rows->pluck('id')->all());

        $subtreeIds = [];
        foreach ($categoryIds as $categoryId) {
            $ids = [];
            $stack = [$categoryId];
            while ($stack) {
                $currentId = array_pop($stack);
                $ids[] = $currentId;
                foreach ($childrenMap[$currentId] ?? [] as $childId) {
                    $stack[] = $childId;
                }
            }

            $subtreeIds[$categoryId] = $ids;
        }

        return $subtreeIds;
    }
}
