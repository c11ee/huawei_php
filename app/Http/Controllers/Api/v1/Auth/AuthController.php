<?php

namespace App\Http\Controllers\Api\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Auth\AppUser;
use App\Models\Auth\AppUserAuth;
use App\Services\Aliyun\DypnsapiService;
use App\Traits\TokenTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use TokenTrait;

    protected DypnsapiService $authService;

    public function __construct(DypnsapiService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 发送登录验证码
     */
    public function sendLoginCode(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
        ], [
            'mobile.required' => '请输入手机号',
            'mobile.regex' => '手机号格式错误',
        ]);

        if (!$this->authService->sendLoginCode($validated['mobile'])) {
            return ApiResponse::error('验证码发送失败，请稍后重试');
        }

        return ApiResponse::success([]);
    }

    /**
     * 登录
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
            'code' => 'required|digits:6',
        ], [
            'mobile.required' => '请输入手机号',
            'mobile.regex' => '手机号格式错误',
            'code.required' => '请输入验证码',
            'code.digits' => '验证码格式错误',
        ]);

        $mobile = $validated['mobile'];
        $verify = $this->authService->checkCode($mobile, $validated['code']);
        if (!$verify) {
            return ApiResponse::error('验证码错误');
        }

        $user = AppUser::where('mobile', $mobile)->first();

        if ($user && $user->status == 0) {
            return ApiResponse::error('用户已被禁用');
        }

        // 是否第一次登录,需要在 app_user, app_user_auth 表中创建用户
        if (!$user) {
            $user = DB::transaction(function () use ($mobile) {
                $user = AppUser::create([
                    'mobile' => $mobile,
                ]);
                AppUserAuth::create([
                    'user_id'       => $user->id,
                    'identity_type' => AppUserAuth::IDENTITY_TYPE_MOBILE,
                    'identifier'    => $mobile,
                    'last_login_at' => now(),
                ]);

                return $user;
            });
        } else {
            AppUserAuth::updateOrCreate([
                'identity_type' => AppUserAuth::IDENTITY_TYPE_MOBILE,
                'identifier'    => $mobile,
            ], [
                'user_id'       => $user->id,
                'last_login_at' => now(),
            ]);
        }

        return ApiResponse::success($this->issueTokens($user), '登录成功');
    }

    /**
     * 刷新 Token
     */
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

    /**
     * 退出登录
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // 删除当前 Token
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success([], '退出成功');
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success([
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => $user->avatar,
            // 手机号脱敏
            'mobile' => $user->mobile ? substr_replace($user->mobile, '****', 3, 4) : '',
        ]);
    }
}
