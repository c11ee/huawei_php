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
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'strike_price' => 'decimal:2',
        // 'status' => 'boolean',
    ];

    protected $hidden = [
        // 规格信息统一由 sku_specs 输出, 不再返回原始规格值关联
        // 关联按方法名隐藏, 序列化后对应 spec_values
        'specValues',
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
     * 规格列表: 合并规格 ID 与文本, 形如
     * [['spec_id' => 16, 'spec_name' => '颜色', 'spec_value_id' => 45, 'spec_value' => '雪域白']]
     */
    public function getSkuSpecsAttribute(): array
    {
        $specValues = $this->relationLoaded('specValues')
            ? $this->specValues
            : $this->specValues()->with('spec')->get();

        // 已预加载 specValues 但未带 spec 关联时, 重新查询一次, 避免逐条懒加载
        if ($specValues->isNotEmpty() && !$specValues->first()->relationLoaded('spec')) {
            $specValues = $this->specValues()->with('spec')->get();
        }

        return $specValues
            ->sortBy(fn($item) => $item->spec?->sort ?? 0)
            ->map(fn($item) => [
                'spec_id'       => $item->pivot->spec_id,
                'spec_name'     => $item->spec?->name ?? '',
                'spec_value_id' => $item->id,
                'spec_value'    => $item->value,
            ])
            ->values()
            ->all();
    }

    /**
     * 追加规格映射字段
     */
    protected function getArrayableAppends()
    {
        return array_merge(parent::getArrayableAppends(), ['sku_specs']);
    }
}
