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
     * 正查: 当前 SKU 拥有的所有规格项
     */
    public function specs(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductSpec::class,
            'product_sku_spec_value',
            'sku_id',
            'spec_id',
        )->select('product_spec.id', 'product_spec.name');
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

    /**
     * 规格映射: {规格ID: 规格值ID}
     */
    public function getSpecValueMapAttribute(): array
    {
        // 已预加载时直接用，避免触发懒加载把 spec_values 带进输出；否则单独查询
        $specValues = $this->relationLoaded('specValues')
            ? $this->specValues
            : $this->specValues()->get();

        return $specValues->mapWithKeys(
            fn($item) => [$item->pivot->spec_id => $item->id]
        )->all();
    }

    /**
     * 追加规格映射字段
     */
    protected function getArrayableAppends()
    {
        return array_merge(parent::getArrayableAppends(), ['spec_value_map']);
    }
}
