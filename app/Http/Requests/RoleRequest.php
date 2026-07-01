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
            'status' => 'required|integer|in:0,1',
            'description' => 'nullable|string|max:255',
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
            'status.required' => '状态不能为空',
            'status.integer' => '状态必须为整数',
            'status.in' => '状态只能为0或1',
            'description.max' => '角色描述不能超过255个字符',
            'description.string' => '角色描述必须为字符串',
            'permission_ids.array' => '权限ID必须为数组',
            'permission_ids.*.integer' => '权限ID必须为整数',
            'permission_ids.*.exists' => '权限ID不存在',
        ];
    }
}
