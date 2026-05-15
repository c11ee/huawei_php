<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * 权限列表
     */
    public function index()
    {
        $query = Permission::query();

        return response()->json([
            // 返回所有数据，不进行分页
            'data' => $query->get(),
        ]);
    }

    /**
     * 创建权限
     */
    public function store(PermissionRequest $request)
    {
        $validated =  $request->validated();

        // $permission = Permission::create([
        //     'name' => $request->name,
        //     'label' => $request->label,
        //     'path' => $request->path,
        //     'icon' => $request->icon,
        //     'type' => $request->type,
        //     'sort' => $request->sort,
        //     'is_auth' => $request->is_auth,
        //     'remark' => $request->remark,
        // ]);

        return response()->json([
            'message' => '验证通过',
            'data' => $validated,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
