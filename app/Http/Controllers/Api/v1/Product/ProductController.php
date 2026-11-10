<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductSku;
use App\Models\Product\ProductSpu;
use App\Services\ProductService;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /** 商品详情 */
    public function show(string $spu_id)
    {
        $product = $this->productService->getProductDetail($spu_id);

        if (!$product) {
            return ApiResponse::error('商品不存在');
        }
        // 前台接口不返回 SKU 成本价
        $product->makeHidden(['creator', 'updater', 'created_at', 'updated_at', 'created_at_ts', 'updated_at_ts'])->skus->makeHidden('cost_price');

        return ApiResponse::success($product);
    }

    /** 根据 SKU ID 获取所属 SPU 的可选规格项与 SKU 列表(购物车切换规格用) */
    public function getSpecValues(string $sku_id)
    {
        $sku = ProductSku::findOrFail($sku_id);

        // specs 关联自带 values; skus 需预加载规格链, 供 sku_specs 计算
        $spu = ProductSpu::with(['specs', 'skus.specValues.spec'])->findOrFail($sku->spu_id);

        // 前台接口不返回 SKU 成本价
        $spu->skus->makeHidden('cost_price');

        // 只返回规格树与 SKU 列表
        return ApiResponse::success([
            'specs' => $spu->specs,
            'skus'  => $spu->skus,
        ]);
    }
}
