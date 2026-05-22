<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Http\Responses\ApiResponse;
use App\Traits\ModelDeleteTrait;
use App\Traits\TreeTrait;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use  TreeTrait, ModelDeleteTrait;

    /**
     * 权限列表
     */
    public function index()
    {
        $query = Permission::query();

        $list = $query->orderBy('sort', 'desc')->get()->map(fn($item) => $this->formatPermission($item))->all();

        return ApiResponse::success($this->handleTreeData($list));
    }

    /**
     * 创建权限
     */
    public function store(PermissionRequest $request)
    {
        Permission::create($this->toModelData($request->validated()));
        return ApiResponse::success([], '添加成功');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PermissionRequest $request, string $id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return ApiResponse::error("数据不存在");
        }
        $permission->update($this->toModelData($request->validated()));
        return ApiResponse::success([], '更新成功');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!$id) {
            return ApiResponse::error("参数错误");
        }
        $ids = array_values(array_filter(explode(',', $id), fn($v) => $v !== ''));
        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        $idsToDelete = $this->collectIdsWithDescendants(Permission::class, $ids);

        Permission::destroy($idsToDelete);
        return ApiResponse::success([], '删除成功');
    }

    /**
     * 请求参数 → 模型字段
     */
    private function toModelData(array $validated): array
    {
        return [
            'name' => $validated['key'],
            'label' => $validated['name'],
            'path' => $validated['path'] ?? '',
            'icon' => $validated['icon'] ?? '',
            'type' => $validated['type'],
            'sort' => $validated['sort'],
            'is_auth' => $validated['is_auth'],
            'remark' => $validated['remark'] ?? '',
            'parent_id' => $validated['parent_id'] ?? 0,
        ];
    }

    /**
     * 模型 → API 出参
     */
    private function formatPermission(Permission $item): array
    {
        return [
            'id' => $item->id,
            'key' => $item->name,
            'name' => $item->label,
            'path' => $item->path,
            'icon' => $item->icon,
            'type' => $item->type,
            'sort' => $item->sort,
            'is_auth' => $item->is_auth,
            'remark' => $item->remark,
            'parent_id' => $item->parent_id,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
