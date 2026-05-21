<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Role::query();

        // 搜索
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        $list = $query->paginate(
            $request->input('limit', 10),
            ['*'],
            'page',
            $request->input('page', 1)
        );

        return $this->success([
            'data' => $list->items(),
            'total' => $list->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        dd($request->permission_ids);
        // $role = Role::create($request->validated());
        // $role->permissions()->sync($request->permission_ids);
        // return $this->success([], '添加成功');
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
