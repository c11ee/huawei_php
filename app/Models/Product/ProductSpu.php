<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSpu extends BaseModel
{
    use SoftDeletes;

    protected $table = 'product_spu';
    protected $guarded = [];

    protected $casts = [
        'slider_images' => 'array',
        'default_sku_sale_price' => 'decimal:2',
        'min_sale_price' => 'decimal:2',
        'max_sale_price' => 'decimal:2',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class, 'spu_id')->orderBy('sort');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ProductSpec::class, 'spu_id')->with('values')->orderBy('sort');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(ProductDetail::class, 'owner_id')->where('owner_type', 'spu');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_spu_category',
            'spu_id',
            'category_id'
        );
    }
}
