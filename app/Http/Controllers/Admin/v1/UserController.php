<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 预加载 role 关联关系
        $query = User::query()->with('role');

        // 搜索
        if ($request->username) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        $list = $query->paginate(
            $request->input('limit', 10),
        );

        return ApiResponse::success([
            'data' => UserResource::collection($list),
            'page' => $list->currentPage(),
            'total' => $list->total(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'phone' => $request->phone,
            'status' => $request->status,
            'role_id' => $request->role_id,
            'password' => bcrypt($request->password),
            'avatar' => $request->avatar ?? '',
        ];
        User::create($data);

        return ApiResponse::success([], '添加成功');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return ApiResponse::error('用户不存在', 404);
        }

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, string $id)
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return ApiResponse::error('用户不存在', 404);
        }

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'phone' => $request->phone,
            'status' => $request->status,
            'role_id' => $request->role_id,
            'avatar' => $request->avatar ?? '',
        ];
        if ($request->password) {
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);

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

        User::destroy($ids);

        return ApiResponse::success([], '删除成功');
    }
}
