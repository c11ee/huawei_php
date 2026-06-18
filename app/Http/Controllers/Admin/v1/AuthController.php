<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Traits\TreeTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    use  TreeTrait;

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

        // 有效期较短的 token
        $accessTokenInstance = $user->createToken('access_token', ['access-api'], now()->addDays(1));
        // 生成 Token (plainTextToken 是纯文本字符串)
        $accessToken = $accessTokenInstance->plainTextToken;

        // 有效期较长的 token
        $refreshTokenInstance = $user->createToken('refresh_token', ['refresh-token'], now()->addDays(7));
        $refreshToken = $refreshTokenInstance->plainTextToken;

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addDays(1)->timestamp,
        ], '登录成功');
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // 可选：删除当前使用的 refresh token（一次性使用）
        $request->user()->currentAccessToken()->delete();

        // 重新颁发新的短效 Access Token + 长效 Refresh Token
        $newAccessToken = $user->createToken('access_token', ['access-api'], now()->addDays(1))->plainTextToken;
        $newRefreshToken = $user->createToken('refresh_token', ['refresh-token'], now()->addDays(7))->plainTextToken;

        return ApiResponse::success([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_at' => now()->addDays(1)->timestamp,
        ]);
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

        return ApiResponse::success(new UserResource($user));
    }

    /** 获取用户权限 */
    public function getPermissions(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('未登录', 401);
        }

        // 通过 role 关联拿到角色，再拿到角色拥有的权限，最后提取出 name 数组
        $separated = [
            'menuPermissions' => [],
            'buttonPermissions' => [],
        ];
        $permission = [];
        if ($user->hasRole('超级管理员')) {
            $permission = Permission::all();
        } else if ($user->roles) {
            $permission = $user->getAllPermissions();
        }
        $formattedPermissions = PermissionResource::collection($permission)->resolve();
        $separated = $this->separatePermissions($formattedPermissions);

        return ApiResponse::success([
            'button_permissions' => $separated['buttonPermissions'],
            'menu_permissions' => $separated['menuPermissions'],
        ]);
    }

    /** 根据传入的权限列表, 区分菜单和按钮权限数组 */
    public function separatePermissions(array $permissions): array
    {
        $menuPermissions = [];
        $buttonPermissions = [];
        foreach ($permissions as $permission) {
            if ($permission['type'] == 1) {
                $menuPermissions[] = $permission;
            } else {
                $buttonPermissions[] = $permission['key'];
            }
        }
        return [
            'menuPermissions' => $this->buildTree($menuPermissions),
            'buttonPermissions' => $buttonPermissions,
        ];
    }
}
