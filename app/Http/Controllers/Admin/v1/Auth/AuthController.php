<?php

namespace App\Http\Controllers\Admin\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\System\User;
use App\Traits\PermissionPayloadTrait;
use App\Traits\TokenTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use TokenTrait, PermissionPayloadTrait;

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
            return ApiResponse::error('用户名或密码错误');
        }

        if ($user && $user->status == 0) {
            return ApiResponse::error('用户已被禁用');
        }

        return ApiResponse::success($this->issueTokens($user), '登录成功');
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();

        if ($user->status == 0) {
            return ApiResponse::error('用户已被禁用');
        }

        // 删除当前使用的 refresh token（不可重复使用），其他设备会话不受影响
        $request->user()->currentAccessToken()->delete();

        // 重新颁发新的短效 Access Token + 长效 Refresh Token
        return ApiResponse::success($this->issueTokens($user));
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // 删除当前 Token
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success([], '退出成功');
    }

    public function getUserInfo(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('未登录', 401);
        }

        return ApiResponse::success(new UserResource($user));
    }

    /** 获取用户权限 */
    public function getPermissions(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('未登录', 401);
        }


        return ApiResponse::success($this->permissionPayload($user));
    }
}
