<?php

namespace App\Http\Controllers\Admin\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * 分类列表（树形，不分页）
     */
    public function index()
    {
        $tree = ProductCategory::where('parent_id', 0)
            ->with('children')
            ->orderBy('sort')
            ->get();

        return ApiResponse::success($tree);
    }

    /**
     * 新增分类
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if ($data['parent_id'] > 0 && ! ProductCategory::whereKey($data['parent_id'])->exists()) {
            return ApiResponse::error('父分类不存在');
        }

        ProductCategory::create($data);

        return ApiResponse::success([], '添加成功');
    }

    /**
     * 更新分类
     */
    public function update(Request $request, string $id)
    {
        $category = ProductCategory::find($id);
        if (! $category) {
            return ApiResponse::error('数据不存在');
        }

        $data = $this->validateData($request);

        if ($data['parent_id'] > 0) {
            if (! ProductCategory::whereKey($data['parent_id'])->exists()) {
                return ApiResponse::error('父分类不存在');
            }

            // 父分类不能是自身或自己的子孙分类，避免出现环
            if (in_array($data['parent_id'], $this->descendantIds((int) $category->id), true)) {
                return ApiResponse::error('父分类不能是自身或其子分类');
            }
        }

        $category->update($data);

        // 状态变更时, 级联同步到所有子孙分类
        if ($category->wasChanged('status')) {
            $childIds = array_values(array_diff(
                $this->descendantIds((int) $category->id),
                [(int) $category->id]
            ));

            if ($childIds !== []) {
                ProductCategory::whereIn('id', $childIds)->update([
                    'status' => $category->status,
                ]);
            }
        }

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

        $deleteIds = [];
        foreach ($ids as $categoryId) {
            $deleteIds = array_merge($deleteIds, $this->descendantIds($categoryId));
        }

        ProductCategory::whereIn('id', array_values(array_unique($deleteIds)))->delete();

        return ApiResponse::success([], '删除成功');
    }

    /**
     * 校验并格式化请求数据
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'category_name' => 'required|string|max:100',
            'parent_id' => 'nullable|integer|min:0',
            'sort' => 'nullable|integer',
            'status' => 'nullable|integer',
            'icon' => 'nullable|string|max:255',
        ], [
            'category_name.required' => '分类名称不能为空',
            'category_name.max' => '分类名称不能超过100个字符',
            'parent_id.integer' => '父分类ID必须为整数',
            'parent_id.min' => '父分类ID不能小于0',
            'sort.integer' => '排序值必须为整数',
            'status.integer' => '状态必须为整数',
            'icon.string' => '图标必须为字符串',
            'icon.max' => '图标不能超过255个字符',
        ]);

        return [
            'category_name' => $data['category_name'],
            'parent_id' => (int) ($data['parent_id'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
            'icon' => $data['icon'] ?? '',
        ];
    }

    /**
     * 收集分类自身及其所有子孙分类 ID
     */
    private function descendantIds(int $categoryId): array
    {
        $ids = [$categoryId];

        while (true) {
            $childIds = ProductCategory::whereIn('parent_id', $ids)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $newIds = array_values(array_diff($childIds, $ids));

            if ($newIds === []) {
                break;
            }

            $ids = array_merge($ids, $newIds);
        }

        return $ids;
    }
}
