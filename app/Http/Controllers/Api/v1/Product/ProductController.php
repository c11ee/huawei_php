<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ProductService;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function show(string $spu_id)
    {
        $product = $this->productService->getProductDetail($spu_id);

        // 前台接口不返回 SKU 成本价
        $product->skus->makeHidden('cost_price');

        return ApiResponse::success($product);
    }
}
