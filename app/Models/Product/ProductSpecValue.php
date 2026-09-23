<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSpecValue extends BaseModel
{
    protected $table = 'product_spec_value';
    protected $guarded = [];

    /**
     * 反查: 获取拥有该规格值的全部 SKU
     */
    public function skus(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductSku::class,
            'product_sku_spec_value',
            'spec_value_id',
            'sku_id'
        )->withPivot(['spu_id', 'spec_id']);
    }
}
