<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 为空未提示错误信息
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => '请输入用户名',
            'password.required' => '请输入密码',
        ]);

        $credentials = $request->only('username', 'password');

        if (!Auth::attempt($credentials)) {
            return ApiResponse::error('用户名或密码错误', 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->status != 1) {
            Auth::logout();
            return ApiResponse::error('用户已禁用', 401);
        }

        // 通过 role 关联拿到角色，再拿到角色拥有的权限，最后提取出 name 数组
        $permissions = [];
        if ($user->role) {
            $permissions = $user->role->permissions()->pluck('name')->toArray();
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'permissions' => $permissions,
        ], '登录成功');
    }

    public function logout()
    {
        Auth::logout();

        return ApiResponse::success([], '退出成功');
    }

    public function getUserInfo()
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponse::error('未登录', 401);
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
        ]);
    }
}
