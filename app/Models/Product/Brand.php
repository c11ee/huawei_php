<?php

namespace App\Models\Product;

use App\Models\BaseModel;

class Brand extends BaseModel
{
    protected $table = 'brand';

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
