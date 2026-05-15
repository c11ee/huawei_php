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
        $data = [
            'name' => $validated['key'],
            'label' => $validated['name'],
            'path' => $validated['path'],
            'icon' => $validated['icon'],
            'type' => $validated['type'],
            'sort' => $validated['sort'],
            'is_auth' => $validated['is_auth'],
            'remark' => $validated['remark'],
        ];
        Permission::create($data);

        return response()->json([
            'message' => '添加成功',
            'data' => [],
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
