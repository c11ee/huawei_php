<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'path' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'type' => 'required|integer|in:1,2',
            'sort' => 'required|integer|min:0',
            'is_auth' => 'required|integer|in:1,2',
            'remark' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '权限名称不能为空',
            'path.string' => '权限路径必须为字符串',
            'icon.string' => '权限图标必须为字符串',
            'type.required' => '权限类型不能为空',
            'type.in' => '权限类型必须为1或2',
            'sort.required' => '排序不能为空',
            'sort.integer' => '排序必须为整数',
            'sort.min' => '排序必须大于0',
            'is_auth.required' => '是否认证不能为空',
            'is_auth.in' => '是否验证必须为1或2',
            'remark.string' => '备注必须为字符串',
        ];
    }
}
