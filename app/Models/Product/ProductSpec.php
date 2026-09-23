<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpec extends BaseModel
{
    protected $table = 'product_spec';
    protected $guarded = [];

    public function values(): HasMany
    {
        return $this->hasMany(ProductSpecValue::class, 'spec_id')->orderBy('sort');
    }
}
