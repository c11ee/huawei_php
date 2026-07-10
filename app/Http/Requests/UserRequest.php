<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            'nickname' => 'required|string|max:255',
            'username' => 'required|string|max:255|min:4',
            'phone' => [
                'nullable',
                'string',
                'regex:/^1[3-9]\d{9}$/',
                // 校验是否有重复
                Rule::unique('users', 'phone')
                    // 校验唯一时, 忽略某一行
                    ->ignore(
                        $this->isMethod('POST') ? null : $this->route('id')
                    ),
            ],
            'email' => [
                'nullable',
                'string',
                // 校验是否有重复
                Rule::unique('users', 'email')
                    // 校验唯一时, 忽略某一行
                    ->ignore(
                        $this->isMethod('POST') ? null : $this->route('id')
                    ),
            ],
            'status' => 'required|in:0,1',
            'role_ids' => 'required|string',
            'password' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'nullable',
                'string',
                'min:5',
                'max:128',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.required' => '请输入昵称',
            'username.required' => '请输入用户名',
            'phone.regex' => '手机号格式错误',
            'phone.unique' => '手机号已存在',
            'email.unique' => '邮箱已存在',
            'status.required' => '请选择状态',
            'role_ids.required' => '请选择角色',
            'password.required' => '请输入密码',
            'password.min' => '密码长度不能小于5位',
            'password.max' => '密码长度不能大于128位',
        ];
    }
}
