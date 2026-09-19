<?php

namespace App\Http\Controllers\Admin\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductBrand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $brands = ProductBrand::query()->paginate($request->input('limit', 10));
        return ApiResponse::success([
            'data' => $brands->items(),
            'page' => $brands->currentPage(),
            'total' => $brands->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        ProductBrand::create($data);
        return ApiResponse::success([], '添加成功');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $this->validateData($request);
        $brand = ProductBrand::query()->find($id);
        $brand->update($data);
        return ApiResponse::success([], '更新成功');
    }

    /**
     * 删除分类（支持逗号分隔批量删除，连同子孙分类一并删除）
     */
    public function destroy(string $id)
    {
        $ids = array_values(array_filter(
            array_map('intval', explode(',', $id)),
            fn($v) => $v > 0
        ));

        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        ProductBrand::whereIn('id', array_values(array_unique($ids)))->delete();

        return ApiResponse::success([], '删除成功');
    }

    /**
     * 编辑状态
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
            'status' => 'required|integer',
        ], [
            'ids.required' => '品牌ID不能为空',
            'status.required' => '状态不能为空',
            'status.integer' => '状态必须为整数',
        ]);

        $ids = array_values(array_filter(
            array_map('intval', explode(',', $request->ids)),
            fn($v) => $v > 0
        ));
        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }



        $updated = ProductBrand::whereIn('id', $ids)->update([
            'status' => (int) $request->status,
        ]);

        if ($updated === 0) {
            return ApiResponse::error('更新失败');
        }

        return ApiResponse::success([], '更新成功');
    }

    /**
     * 校验并格式化请求数据
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'logo' => 'nullable|string|max:255',
            'sort' => 'nullable|integer',
            'status' => 'nullable|integer',
        ], [
            'name.required' => '品牌名称不能为空',
            'name.max' => '品牌名称不能超过100个字符',
            'logo.string' => 'logo必须为字符串',
            'logo.max' => 'logo不能超过255个字符',
            'sort.integer' => '排序值必须为整数',
            'status.integer' => '状态必须为整数',
        ]);

        return [
            'name' => $data['name'],
            'logo' => $data['logo'] ?? '',
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
        ];
    }
}
