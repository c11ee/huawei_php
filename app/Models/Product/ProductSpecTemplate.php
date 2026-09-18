<?php

namespace App\Models\Product;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecTemplate extends BaseModel
{
    protected $table = 'product_spec_template';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sort',
        'status',
        'spec_json',
        'created_by',
        'updated_by',
    ];

    /**
     * 属性转换
     */
    protected $casts = [
        'spec_json' => 'json',
    ];

    /**
     * 创建人
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 更新人
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
