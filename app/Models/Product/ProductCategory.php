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
}
