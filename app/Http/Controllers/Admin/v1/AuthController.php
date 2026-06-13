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

        // 通过 role 关联拿到角色，再拿到角色拥有的权限，最后提取出 name 数组
        $separated = [
            'menuPermissions' => [],
            'buttonPermissions' => [],
        ];
        if ($user->hasRole('超级管理员')) {
            $separated = $this->separatePermissions(Permission::all()->toArray());
        } else if ($user->roles) {
            $separated =  $this->separatePermissions($user->getAllPermissions()->toArray());
        }

        // 生成 Token (plainTextToken 是纯文本字符串)
        $token = $user->createToken($request->env)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
            'buttonPermissions' => $separated['buttonPermissions'],
            'menuPermissions' => $separated['menuPermissions'],
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
            'user' => new UserResource($user),
            'buttonPermissions' => $separated['buttonPermissions'],
            'menuPermissions' => $separated['menuPermissions'],
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
