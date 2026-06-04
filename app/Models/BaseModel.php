<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * 统一时间格式
     */
    protected function serializeDate(DateTimeInterface $date): string
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
