<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 预加载 permissions 关联关系
        $query = Role::query()->with('permissions');

        // 搜索
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $list = $query->paginate(
            $request->input('limit', 10),
        );

        $data = collect($list->items())->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                // 权限ID列表
                'permissions' => $item->permissions->pluck('id'),
                'created_at' => $this->serializeDate($item->created_at),
                'updated_at' => $this->serializeDate($item->updated_at),
                'created_at_ts' => $item->created_at?->timestamp ?? 0,
                'updated_at_ts' => $item->updated_at?->timestamp ?? 0,
            ];
        })->toArray();

        return ApiResponse::success([
            'data' => $data,
            'page' => $list->currentPage(),
            'total' => $list->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        // 创建角色
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'sanctum',
        ]);

        // 分配权限
        if (!empty($request->permission_ids)) {
            $permissions = Permission::whereIn('id', $request->permission_ids)->get();
            $role->syncPermissions($permissions);
        }

        return ApiResponse::success([], '添加成功');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // 更新角色
        $role = Role::find($id);
        if (!$role) {
            return ApiResponse::error("数据不存在");
        }
        $role->update([
            'name' => $request->name,
        ]);

        // 分配权限
        if (!empty($request->permission_ids)) {
            $permissions = Permission::whereIn('id', $request->permission_ids)->get();
            $role->syncPermissions($permissions);
        }

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

        Role::destroy($ids);

        return ApiResponse::success([], '删除成功');
    }
}
