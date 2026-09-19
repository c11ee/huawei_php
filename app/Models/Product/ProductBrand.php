<?php

namespace App\Models\Product;

use App\Models\BaseModel;

class ProductBrand extends BaseModel
{
    protected $table = 'product_brand';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'logo',
        'sort',
        'status',
    ];
}
