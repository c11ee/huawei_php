<?php

namespace App\Models;

class UserColumnPreference extends BaseModel
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
