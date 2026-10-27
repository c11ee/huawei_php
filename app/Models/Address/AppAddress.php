<?php

namespace App\Models\Address;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppAddress extends BaseModel
{
    protected $table = 'app_address';

    protected $guarded = [];

    /**
     * 追加行政区划名称，前端直接展示，无需再查 sys_area
     */
    protected $appends = [
        'created_at_ts',
        'updated_at_ts',
        'province_name',
        'city_name',
        'district_name',
        'township_name',
    ];

    /**
     * 省份
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(SysArea::class, 'province_id');
    }

    /**
     * 城市
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(SysArea::class, 'city_id');
    }

    /**
     * 区县
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(SysArea::class, 'district_id');
    }

    /**
     * 乡镇/街道
     */
    public function township(): BelongsTo
    {
        return $this->belongsTo(SysArea::class, 'township_id');
    }

    public function getProvinceNameAttribute(): string
    {
        return $this->province?->name ?? '';
    }

    public function getCityNameAttribute(): string
    {
        return $this->city?->name ?? '';
    }

    public function getDistrictNameAttribute(): string
    {
        return $this->district?->name ?? '';
    }

    public function getTownshipNameAttribute(): string
    {
        // township_id 为 0 表示未匹配到街道，不再触发查询
        if (! $this->township_id) {
            return '';
        }

        return $this->township?->name ?? '';
    }
}
