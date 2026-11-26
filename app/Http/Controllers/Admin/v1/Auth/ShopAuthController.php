<?php

namespace App\Http\Controllers\Admin\v1\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Shop\ShopAdmin;
use App\Traits\PermissionPayloadTrait;
use App\Traits\TokenTrait;
use Illuminate\Support\Facades\Hash;

class ShopAuthController extends Controller
{
    use TokenTrait, PermissionPayloadTrait;

    /** 登录 */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ], [
            'username.required' => '请输入用户名',
            'password.required' => '请输入密码'
        ]);

        $admin = ShopAdmin::with('shop')->where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return ApiResponse::error('用户名或密码错误');
        }

        if ($admin->status === 0) {
            return ApiResponse::error('账号已禁用');
        }

        if (!$admin->shop || $admin->shop->status === 2) {
            return ApiResponse::error('店铺已封禁');
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success(array_merge(
            $this->issueTokens($admin),
            [
                'user_type' => 'shop'
            ]
        ));
    }

    public function refreshToken(Request $request)
    {
        $admin = $request->user();

        if ($admin->status === 0) {
            return ApiResponse::error('账号已禁用');
        }

        if (!$admin->shop || $admin->shop->status === 2) {
            return ApiResponse::error('店铺已封禁');
        }

        $admin->currentAccessToken()->delete();

        return ApiResponse::success($this->issueTokens($admin));
    }

    public function logout(Request $request)
    {
        $admin = $request->user();

        if ($admin) {
            // 删除当前 Token
            $admin->currentAccessToken()->delete();
        }

        return ApiResponse::success([], '退出成功');
    }

    public function getUserInfo(Request $request)
    {
        $admin = $request->user();

        return ApiResponse::success([
            'id' => $admin->id,
            'user_type' => 'shop',
            'username' => $admin->username,
            'shop_id' => $admin->shop_id,
            'shop_name' => $admin->shop?->shop_name,
            'status' => $admin->status,
            'role_names' => $admin->roles->pluck('name')->implode(','),
            'last_login_at' => $admin->last_login_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function getPermissions(Request $request)
    {
        $admin = $request->user();

        if (!$admin) {
            return ApiResponse::error('未登录', 401);
        }

        return ApiResponse::success($this->permissionPayload($admin));
    }
}
