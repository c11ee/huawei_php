<?php

namespace App\Http\Controllers\Admin\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\System\User;
use App\Traits\TokenTrait;
use App\Traits\TreeTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    use TokenTrait, TreeTrait;

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
