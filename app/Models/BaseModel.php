<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
