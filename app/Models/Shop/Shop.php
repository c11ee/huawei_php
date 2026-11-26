<?php

namespace App\Models\Shop;

use App\Models\Auth\AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $table = 'shop';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(ShopAdmin::class, 'shop_id');
    }
}
