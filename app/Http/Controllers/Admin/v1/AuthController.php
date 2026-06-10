<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 为空未提示错误信息
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'env' => 'required|string',
        ], [
            'username.required' => '请输入用户名',
            'password.required' => '请输入密码',
            'env.required' => '请输入环境',
        ]);


        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('用户名或密码错误', 401);
        }

        if ($user->status != 1) {
            return ApiResponse::error('用户已禁用', 401);
        }

        // 通过 role 关联拿到角色，再拿到角色拥有的权限，最后提取出 name 数组
        $permissions = [];
        if ($user->role) {
            $permissions = $user->role->permissions()->pluck('name')->toArray();
        }

        // 生成 Token (plainTextToken 是纯文本字符串)
        $token = $user->createToken($request->env)->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'permissions' => $permissions,
        ], '登录成功');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // 删除当前 Token
            $user->currentAccessToken()->delete();
        }


        // Auth::logout();


        return ApiResponse::success([], '退出成功');
    }

    public function getUserInfo(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('未登录', 401);
        }

        // 通过 role 关联拿到角色，再拿到角色拥有的权限，最后提取出 name 数组
        $permissions = [];
        if ($user->role) {
            $permissions = $user->role->permissions()->pluck('name')->toArray();
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'permissions' => $permissions,
        ]);
    }
}
