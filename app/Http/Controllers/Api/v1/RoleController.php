<?php

namespace App\Http\Controllers\Api\v1;

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
        $query = Role::query();

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
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
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
            'guard_name' => 'api',
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
