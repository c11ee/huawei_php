<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSku extends BaseModel
{
    protected $table = 'product_sku';
    protected $guarded = [];

    protected $casts = [
        'spec_json' => 'array',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'strike_price' => 'decimal:2',
        // 'status' => 'boolean',
    ];

    public function spu(): BelongsTo
    {
        return $this->belongsTo(ProductSpu::class, 'spu_id');
    }

    /**
     * 正查: 当前 SKU 拥有的所有规格值
     */
    public function specValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductSpecValue::class,
            'product_sku_spec_value',
            'sku_id',
            'spec_value_id',
        )->withPivot(['spu_id', 'spec_id']);
    }
}
