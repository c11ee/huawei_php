<?php

namespace App\Models\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AppUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'app_user';
    protected $guarded = [];

    /**
     * 所有的登录方式信息
     */
    public function auths()
    {
        return $this->hasMany(AppUserAuth::class, 'user_id', 'id');
    }
}
