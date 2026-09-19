<?php

namespace App\Models\Product;

use App\Models\BaseModel;

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

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->with('children');
    }
}
