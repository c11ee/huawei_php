<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * 权限列表
     */
    public function index()
    {
        $query = Permission::query();

        // 返回所有数据，不进行分页
        return Controller::success($query->get());
    }

    /**
     * 创建权限
     */
    public function store(PermissionRequest $request)
    {
        $validated =  $request->validated();
        $data = [
            'name' => $validated['key'],
            'label' => $validated['name'],
            'path' => $validated['path'] ?? '',
            'icon' => $validated['icon'] ?? '',
            'type' => $validated['type'],
            'sort' => $validated['sort'],
            'is_auth' => $validated['is_auth'],
            'remark' => $validated['remark'] ?? '',
        ];
        Permission::create($data);
        return Controller::success([], '添加成功');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PermissionRequest $request, string $id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return Controller::error("数据不存在");
        }
        $validated =  $request->validated();
        $data = [
            'name' => $validated['key'],
            'label' => $validated['name'],
            'path' => $validated['path'] ?? '',
            'icon' => $validated['icon'] ?? '',
            'type' => $validated['type'],
            'sort' => $validated['sort'],
            'is_auth' => $validated['is_auth'],
            'remark' => $validated['remark'] ?? '',
        ];
        $permission->update($data);
        return Controller::success([], '更新成功');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $ids)
    {
        if (!$ids) {
            return Controller::error("参数错误");
        }
        $ids = explode(',', $ids);

        Permission::destroy($ids);
        return Controller::success([], '删除成功');
    }
}
