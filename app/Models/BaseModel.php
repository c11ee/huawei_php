<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseModel extends Model
{
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * 序列化日期字段时按 app 时区输出，避免 HTTP JSON 响应被默认转为 UTC
     *
     * @param  \DateTimeInterface  $date
     */
    protected function serializeDate($date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 追加时间戳字段
     */
    protected $appends = [
        'created_at_ts',
        'updated_at_ts',
    ];

    /**
     * 创建时间时间戳
     */
    public function getCreatedAtTsAttribute()
    {
        return $this->created_at?->timestamp;
    }

    /**
     * 更新时间时间戳
     */
    public function getUpdatedAtTsAttribute()
    {
        return $this->updated_at?->timestamp;
    }

    /**
     * 仅当对应的时间字段被实际查询出来时，才追加时间戳
     * 避免 select 精简字段时输出值为 null 的 created_at_ts / updated_at_ts
     */
    protected function getArrayableAppends()
    {
        return array_filter(parent::getArrayableAppends(), function ($key) {
            return match ($key) {
                'created_at_ts' => array_key_exists('created_at', $this->attributes),
                'updated_at_ts' => array_key_exists('updated_at', $this->attributes),
                default         => true,
            };
        });
    }

    /**
     * 创建人
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 更新人
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
