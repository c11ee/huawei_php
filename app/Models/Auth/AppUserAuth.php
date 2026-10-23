<?php

namespace App\Models\Auth;

use App\Models\BaseModel;

class AppUserAuth extends BaseModel
{
    /** 认证类型：手机号 */
    public const IDENTITY_TYPE_MOBILE = 2;

    protected $table = 'app_user_auth';
    protected $guarded = [];
}
