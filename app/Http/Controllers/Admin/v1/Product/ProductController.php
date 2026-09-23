<?php

namespace App\Http\Controllers\Admin\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductDetail;
use App\Models\Product\ProductSku;
use App\Models\Product\ProductSkuSpecValue;
use App\Models\Product\ProductSpec;
use App\Models\Product\ProductSpecValue;
use App\Models\Product\ProductSpu;
use App\Models\Product\ProductSpuCategory;
use App\Services\ProductService;
use App\Traits\BatchUpdateStatusTrait;
use App\Traits\OperatorResourceTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use OperatorResourceTrait, BatchUpdateStatusTrait;

    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit   = $request->input('limit', 10);
        $keyword = trim($request->input('keyword', ''));
        // 是否查看回收站商品
        $showTrashed = $request->input('show_trashed', 0);
        // 状态
        $status = $request->input('status', null);

        $query = ProductSpu::query()
            ->when($showTrashed, fn($query) => $query->onlyTrashed())
            ->when($status, fn($query) => $query->where('status', $status))
            ->with('brand', 'creator', 'updater', 'categories')
            ->orderBy('sort');
        if ($keyword) {
            $query->where('product_name', 'like', '%' . $keyword . '%')->orWhere('spu_code', 'like', '%' . $keyword . '$');
        }
        $products = $query->paginate($limit);

        $data = $this->withOperatorResources($products->items());

        // 各状态数量同级
        $counts = [
            'draft' => ProductSpu::where('status', 0)->count(),
            'on_sale' => ProductSpu::where('status', 1)->count(),
            'off_sale' => ProductSpu::where('status', 2)->count(),
            'trashed' => ProductSpu::onlyTrashed()->count(),
        ];
        return ApiResponse::success([
            'data'  => $data,
            'page'  => $products->currentPage(),
            'total' => $products->total(),
            'counts' => $counts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request)
    {
        $this->productService->saveProduct($request->validated());

        return ApiResponse::success([]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, string $id)
    {
        $this->productService->saveProduct($request->validated(), $id);

        return ApiResponse::success([]);
    }

    /**
     * 删除商品
     */
    public function destroy(string $ids, Request $request)
    {
        // 彻底删除
        $forceDelete = $request->input('force_delete', 0);

        $ids = array_values(array_filter(
            array_map('intval', explode(',', $ids)),
            fn($v) => $v > 0
        ));

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        $uniqueIds = array_values(array_unique($ids));
        if ($forceDelete) {
            // 彻底删除
            DB::transaction(function () use ($uniqueIds) {
                ProductSpu::whereIn('id', $uniqueIds)->forceDelete();

                // 删除关联
                ProductDetail::where('owner_type', 'spu')->whereIn('owner_id', $uniqueIds)->delete();
                ProductSku::whereIn('spu_id', $uniqueIds)->delete();
                ProductSkuSpecValue::whereIn('sku_id', $uniqueIds)->delete();
                ProductSpec::whereIn('spu_id', $uniqueIds)->delete();
                ProductSpecValue::whereIn('spu_id', $uniqueIds)->delete();
                ProductSpuCategory::whereIn('spu_id', $uniqueIds)->delete();
            });
        } else {
            // 软删除：先把状态改为下架(2)，再执行软删除（写入 deleted_at）
            ProductSpu::whereIn('id', $uniqueIds)->update(['status' => 2]);
            ProductSpu::whereIn('id', $uniqueIds)->delete();
        }

        return ApiResponse::success([], '删除成功');
    }

    /**
     * 恢复商品
     */
    public function restore(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
        ], [
            'ids.required' => 'ID不能为空',
        ]);
        $ids = array_values(array_filter(
            array_map('intval', explode(',', $request->input('ids'))),
            fn($v) => $v > 0
        ));

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        ProductSpu::withTrashed()->whereIn('id', $ids)->restore();

        return ApiResponse::success([], '恢复成功');
    }

    /**
     * 详情
     */
    public function show(string $id)
    {
        $product = ProductSpu::with('brand', 'creator', 'detail', 'updater', 'categories', 'specs', 'skus')->find($id);
        return ApiResponse::success($product);
    }

    /**
     * 编辑状态
     */
    public function updateStatus(Request $request)
    {
        return $this->batchUpdateStatus($request, ProductSpu::class);
    }
}
