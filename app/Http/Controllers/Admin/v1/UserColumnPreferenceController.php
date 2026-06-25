<?php

namespace App\Http\Controllers\Admin\v1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\UserColumnPreference;
use Illuminate\Http\Request;

class UserColumnPreferenceController extends Controller
{
    /**
     * 根据 key 获取用户的列偏好配置
     */
    public function show(string $key)
    {
        // 因为 key 不是主键，所以用 where 查询
        $preference = UserColumnPreference::where('key', $key)->first();

        if (!$preference) {
            // 返回空数组
            return ApiResponse::success([]);
        }

        // 只需返回配置就行了
        return ApiResponse::success($preference->columns);
    }

    /**
     * 保存或更新列偏好配置（保存即更新）
     */
    public function store(Request $request)
    {
        // 1. 验证数据，确保 columns 传过来的是合法的 JSON/数组 结构
        $validated = $request->validate([
            'key'     => 'required|string|max:255',
            'columns' => 'required|array', // 前端传的 JSON 会被自动识别为 array
        ]);

        // 2. 存储或更新
        UserColumnPreference::updateOrCreate(
            ['key' => $validated['key']],
            ['columns' => $validated['columns']] // 直接传入数组，Model 的 casts 会自动处理成 JSON 字符串存入数据库
        );

        return ApiResponse::success([], '保存成功');
    }
}
