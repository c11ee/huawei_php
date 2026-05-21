<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:roles,name',
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer|exists:permissions,id'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '角色名称不能为空',
            'name.string' => '角色名称必须为字符串',
            'name.max' => '角色名称不能超过255个字符',
            'name.unique' => '角色名称已存在',
            'permission_ids.array' => '权限ID必须为数组',
            'permission_ids.*.integer' => '权限ID必须为整数',
            'permission_ids.*.exists' => '权限ID不存在',
        ];
    }
}
