<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Http\Resources\PermissionResource;
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
        $permissions = Permission::all();

        // 利用 Resource 过滤字段、格式化时间，并通过 resolve() 转为纯扁平数组
        $formattedList = PermissionResource::collection($permissions)->resolve();

        // 将格式化后的干净数组送入递归函数，构造树形数据
        $treeData = $this->buildTree($formattedList);

        // 完美返回
        return ApiResponse::success($treeData);
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
}
