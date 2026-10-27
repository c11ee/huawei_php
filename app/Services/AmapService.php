<?php

namespace App\Services;

use App\Models\Address\SysArea;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AmapService
{
    protected string $url;

    public function __construct()
    {
        $this->url = rtrim((string) config('services.amap.url'), '/');
    }

    /** 
     * 搜索POI 2.0 
     */
    public function searchPoi(array $params): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get($this->url . '/v5/place/text', [
                    'key' => config('services.amap.key'),
                    'keywords' => $params['keyword'],
                    'page_size' => $params['limit'],
                    'city_limit' => true,
                    'region' => $params['city_name'],
                ]);
        } catch (Throwable $e) {
            Log::error('高德搜索POI请求失败:' . $e->getMessage());
            return null;
        }

        if ($response->failed() || (string) $response->json('status') !== '1') {
            Log::error('高德搜索POI返回异常:' . $response->body());
            return null;
        }

        $pois = $response->json('pois', []);
        return $pois;
    }

    /** 地理编码 */
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get($this->url . '/v3/geocode/geo', [
                    'key'     => config('services.amap.key'),
                    'address' => $address,
                ]);
        } catch (Throwable $e) {
            Log::error('高德地理编码请求失败:' . $e->getMessage());
            return null;
        }

        if ($response->failed() || (string) $response->json('status') !== '1') {
            Log::error('高德地理编码返回异常:' . $response->body());
            return null;
        }

        $geocodes = $response->json('geocodes', []);
        return $geocodes;
    }

    /**
     * 经纬度逆地理编码
     *
     * @param string $location 经度,纬度
     */
    public function reverseGeocode(string $location): ?array
    {
        try {
            $response = Http::timeout(5)
                ->get($this->url . '/v3/geocode/regeo', [
                    'key' => config('services.amap.key'),
                    'location' => $location,
                    'extensions' => 'base',
                ]);
        } catch (Throwable $e) {
            Log::error('高德逆地理编码请求失败:' . $e->getMessage());
            return null;
        }

        if ($response->failed() || (string) $response->json('status') !== '1') {
            Log::error('高德逆地理编码返回异常:' . $response->body());
            return null;
        }

        $regeocode = $response->json('regeocode', []);
        $component = $regeocode['addressComponent'] ?? [];

        // 高德无对应数据时会把 adcode/towncode 返回为空数组，先统一成字符串
        $district = $this->findArea($this->toCode($component['adcode'] ?? null));
        $township = $this->findArea($this->toCode($component['towncode'] ?? null));

        // 市、省逐级按 parent_id 上溯，任一层级缺失时后续为 null，不再触发空指针
        $city = $this->findAreaById($district?->parent_id);
        $province = $this->findAreaById($city?->parent_id);

        return [
            'country'     => $component['country'] ?? '',
            'province'    => $province?->name ?? '',
            'province_id' => $province?->id ?? '',
            'city'        => $city?->name ?? '',
            'city_id'     => $city?->id ?? '',
            'district'    => $district?->name ?? '',
            'district_id' => $district?->id ?? '',
            'township'    => $township?->name ?? '',
            'township_id' => $township?->id ?? '',
            'address'     => $regeocode['formatted_address'] ?? '',
        ];
    }

    /**
     * 按高德编码查询行政区划
     */
    private function findArea(string|int|null $code): ?SysArea
    {
        $code = (string) $code;

        // 空编码或顶级 parent_id = 0（省再往上没有节点）直接跳过查询
        if ($code === '' || $code === '0') {
            return null;
        }

        return SysArea::query()
            // ext_id 为 12 位标准编码，短编码在右侧补 0 对齐
            ->where('ext_id', str_pad($code, 12, '0'))
            ->first(['id', 'parent_id', 'name']);
    }

    /**
     * 按主键 id 查询行政区划（用于 parent_id 逐级上溯）
     */
    private function findAreaById(?int $id): ?SysArea
    {
        if (! $id) {
            return null;
        }

        return SysArea::find($id, ['id', 'parent_id', 'name']);
    }

    /**
     * 高德编码字段统一转字符串，兼容空数组、null 等异常返回
     */
    private function toCode(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
