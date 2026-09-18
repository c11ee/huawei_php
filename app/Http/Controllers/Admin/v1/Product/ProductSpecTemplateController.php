<?php

namespace App\Http\Controllers\Admin\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductSpecTemplate;
use Illuminate\Http\Request;

class ProductSpecTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $keyword = trim($request->input('keyword', ""));

        $templates = ProductSpecTemplate::query()->with(['creator', 'updater'])->when($keyword, function ($query) use ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")->orWhere('spec_json', 'like', "%{$keyword}%");
            });
        })->paginate($request->input('limit', 10));

        $data = collect($templates->items())->map(function ($item) {
            $arr = $item->toArray();
            $arr['creator'] = $item->creator ? new UserResource($item->creator) : null;
            $arr['updater'] = $item->updater ? new UserResource($item->updater) : null;
            return $arr;
        });

        return ApiResponse::success([
            'data' => $data,
            'page' => $templates->currentPage(),
            'total' => $templates->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        ProductSpecTemplate::create($data);
        return ApiResponse::success([], '添加成功');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $this->validateData($request);
        $brand = ProductSpecTemplate::query()->find($id);
        $brand->update($data);
        return ApiResponse::success([], '更新成功');
    }

    /**
     * 删除规格模板（支持逗号分隔批量删除，连同子孙规格模板一并删除）
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

        ProductSpecTemplate::whereIn('id', array_values(array_unique($ids)))->delete();

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
            'ids.required' => '规格模板ID不能为空',
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



        $updated = ProductSpecTemplate::whereIn('id', $ids)->update([
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
            'spec_json' => 'nullable|array',
            'sort' => 'nullable|integer',
            'status' => 'nullable|integer',
        ], [
            'name.required' => '规格模板名称不能为空',
            'name.max' => '规格模板名称不能超过100个字符',
            'spec_json.array' => '规格JSON必须为数组',
            'sort.integer' => '排序值必须为整数',
            'status.integer' => '状态必须为整数',
        ]);

        return [
            'name' => $data['name'],
            'spec_json' => $data['spec_json'] ?? [],
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
            'updated_by' => $request->user()->id,
        ];
    }
}
