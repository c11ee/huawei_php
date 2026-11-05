<?php

namespace App\Models\Cart;

use App\Models\Auth\AppUser;
use App\Models\BaseModel;
use App\Models\Product\ProductSku;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppCartItem extends BaseModel
{
    /**
     * 类名推导出的表名是 app_cart_items，与迁移中的 app_cart_item 不一致，需显式指定
     */
    protected $table = 'app_cart_item';

    protected $guarded = [];

    protected $casts = [
        'quantity'       => 'integer',
        'snapshot_json'  => 'array',
    ];

    /**
     * 所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    /**
     * 对应的 SKU
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }

    /**
     * 商品展示信息: 优先使用实时数据, 商品被删除时回退到快照数据
     */
    public function getProductAttribute(): array
    {
        $snapshot = $this->snapshot_json ?: [];
        $sku = $this->sku;

        // SKU 或所属 SPU 已被删除(含软删): 完全依赖快照
        if (!$sku || !$sku->spu) {
            return array_merge([
                'spu_id'     => 0,
                'spu_name'   => '',
                'sku_name'   => '',
                'image_url'  => '',
                'sale_price' => 0,
                'stock'      => 0,
                'sku_specs'  => [],
                'brand_id'   => 0,
                'brand_name' => '',
                'brand_logo' => '',
            ], $snapshot, ['offline_reason' => 'deleted']);
        }

        $brand = $sku->spu?->brand;

        // 是否下架
        $status = $sku->spu->trashed() || $sku->status !== 1 || $sku->spu->status !== 1;
        // 是否还有库存
        $stock = $sku->stock > 0;
        return [
            'spu_id'         => $sku->spu_id ?? ($snapshot['spu_id'] ?? 0),
            'spu_name'       => $sku->spu?->product_name ?: ($snapshot['spu_name'] ?? ''),
            'sku_name'       => $sku->name,
            'image_url'      => $sku->image_url ?: ($snapshot['image_url'] ?? ''),
            'sale_price'     => $sku->sale_price,
            'stock'          => $sku->stock,
            'sku_specs'      => $sku->sku_specs,
            'brand_id'       => $sku->spu?->brand_id ?? ($snapshot['brand_id'] ?? 0),
            'brand_name'     => $brand?->name ?: ($snapshot['brand_name'] ?? ''),
            'brand_logo'     => $brand?->logo ?: ($snapshot['brand_logo'] ?? ''),
            'offline_reason' => $status ? 'offline' : (!$stock ? 'out_of_stock' : ''),
        ];
    }
}
