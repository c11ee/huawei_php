<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserColumnPreference extends Model
{
    protected $table = 'user_column_preference';

    protected $fillable = ['key', 'columns'];

    /**
     * 属性转换
     */
    protected $casts = [
        'columns' => 'json',
    ];
}
