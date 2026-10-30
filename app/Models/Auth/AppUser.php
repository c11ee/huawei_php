<?php

namespace App\Models\Auth;

use App\Models\Address\AppAddress;
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

    /**
     * 所有的地址信息
     */
    public function addresses()
    {
        return $this->hasMany(AppAddress::class, 'user_id', 'id')->orderBy('is_default', 'desc');
    }
}
