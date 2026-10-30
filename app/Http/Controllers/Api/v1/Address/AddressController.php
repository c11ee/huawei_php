<?php

namespace App\Http\Controllers\Api\v1\Address;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Address\SysArea;
use App\Services\Aliyun\AddresspurificationService;
use App\Services\AmapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /** 经纬度逆地理编码 */
    public function reverseGeocode(Request $request, AmapService $amap)
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'regex:/^-?\d{1,3}(?:\.\d+)?,-?\d{1,2}(?:\.\d+)?$/'],
        ]);

        $result = $amap->reverseGeocode($validated['location']);

        if (!$result) {
            return ApiResponse::error('逆地理编码失败', 500);
        }

        return ApiResponse::success($result);
    }

    /** 
     * 搜索POI 2.0 
     */
    public function searchPoi(Request $request, AmapService $amap)
    {
        $validated = $request->validate([
            'city_name' => 'required|string',
            'keyword'   => 'required|string|min:1|max:80',
            'limit'     => 'required|integer|min:1|max:25',
        ]);

        $result = $amap->searchPoi($validated);

        // if (!$result) {
        //     return ApiResponse::error('搜索POI失败', 500);
        // }

        return ApiResponse::success($result);
    }

    /** 从地址提取快递信息 */
    public function extractExpress(Request $request, AddresspurificationService $service)
    {
        $validated = $request->validate([
            'address' => 'required|string',
        ]);

        $result = $service->extractExpress($validated['address']);
        if (!$result) {
            return ApiResponse::error('从地址提取快递信息失败', 500);
        }

        return ApiResponse::success($result);
    }

    /** 返回省市区数据 */
    public function getArea(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|min:0',
        ]);
        $parentId = (int) ($validated['parent_id'] ?? 0);

        $cacheKey = "sys_area:children:parent_{$parentId}";

        $result = Cache::tags('sys_area')->remember($cacheKey, now()->addDay(), function () use ($parentId) {
            return SysArea::where('parent_id', $parentId)->where('status', 1)->select('id', 'parent_id', 'name')->get();
        });

        return ApiResponse::success($result);
    }

    /** 热门城市 */
    public function getHotCities()
    {
        // 按展示顺序维护，使用 12 位国标编码匹配，避免同名城市误命中
        $hotCityCodes = [
            '110100000000', // 北京
            '310100000000', // 上海
            '440100000000', // 广州
            '440300000000', // 深圳
            '330100000000', // 杭州
            '510100000000', // 成都
            '420100000000', // 武汉
            '610100000000', // 西安
        ];

        $order = array_flip($hotCityCodes);
        $result = Cache::tags('sys_area')->remember('sys_area:hot_cities', now()->addDay(), function () use ($hotCityCodes, $order) {
            return  SysArea::where('level', 1)
                ->where('status', 1)
                ->whereIn('ext_id', $hotCityCodes)
                ->select('id', 'parent_id', 'name', 'ext_id')
                // 预加载所属省份，避免序列化时逐条查询
                ->with('parent:id,name')
                ->get()
                ->sortBy(fn(SysArea $area) => $order[$area->ext_id] ?? PHP_INT_MAX)
                ->map(fn(SysArea $area) => [
                    'city_id'       => $area->id,
                    'city_name'     => $area->name,
                    'province_id'   => $area->parent_id,
                    'province_name' => $area->parent?->name ?? '',
                ])
                ->values();
        });

        return ApiResponse::success($result);
    }

    /** 用户地址列表 */
    public function index(Request $request)
    {
        $user = $request->user();

        // 预加载区划，避免序列化名称时逐条查询
        $addresses = $user->addresses()
            ->with(['province:id,name', 'city:id,name', 'district:id,name', 'township:id,name'])
            ->get();

        return ApiResponse::success($addresses);
    }

    /** 用户地址详情 */
    public function show(Request $request, string $id)
    {
        // 限定在当前用户名下查询，避免越权查看他人地址
        $address = $request->user()->addresses()->find($id);
        if (!$address) {
            return ApiResponse::error('地址不存在', 404);
        }

        return ApiResponse::success($address);
    }

    /** 用户地址删除 */
    public function destroy(Request $request, string $id)
    {
        // 限定在当前用户名下查询，避免越权删除他人地址
        $address = $request->user()->addresses()->find($id);
        if (!$address) {
            return ApiResponse::error('地址不存在', 404);
        }

        $address->delete();

        return ApiResponse::success();
    }

    /** 用户地址设置默认 */
    public function setDefault(Request $request, string $id)
    {
        $user = $request->user();

        // 限定在当前用户名下查询，避免越权操作他人地址
        $address = $user->addresses()->find($id);
        if (!$address) {
            return ApiResponse::error('地址不存在', 404);
        }

        DB::transaction(function () use ($user, $address) {
            // 先取消其他地址的默认状态，保证全局只有一条默认地址
            $user->addresses()->whereKeyNot($address->id)->update(['is_default' => 0]);
            $address->update(['is_default' => 1]);
        });

        return ApiResponse::success();
    }

    /** 用户地址创建 */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        $user = $request->user();

        $address = DB::transaction(function () use ($user, $validated) {
            // 用户还没有任何地址时，第一条自动设为默认
            if (! $user->addresses()->exists()) {
                $validated['is_default'] = 1;
            }

            // 新增的地址设为默认时，先取消其他地址的默认状态，保证全局只有一条默认地址
            if ($validated['is_default'] === 1) {
                $user->addresses()->where('is_default', 1)->update(['is_default' => 0]);
            }

            return $user->addresses()->create($validated);
        });

        return ApiResponse::success($address);
    }

    /** 用户地址更新 */
    public function update(Request $request, string $id)
    {
        $validated = $this->validateData($request);
        $user = $request->user();

        // 限定在当前用户名下查询，避免越权修改他人地址
        $address = $user->addresses()->find($id);
        if (!$address) {
            return ApiResponse::error('地址不存在', 404);
        }

        DB::transaction(function () use ($user, $address, $validated) {
            // 更新为默认地址时，取消该用户其他地址的默认状态
            if ($validated['is_default'] === 1) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => 0]);
            }

            $address->update($validated);
        });

        return ApiResponse::success($address);
    }

    /**
     * 校验并格式化请求数据
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64',
            'mobile'      => 'required|regex:/^1[3-9]\d{9}$/',
            'province_id' => 'required|integer|min:1',
            'city_id'     => 'required|integer|min:1',
            'district_id' => 'required|integer|min:1',
            'township_id' => 'nullable|integer|min:0',
            'address'     => 'required|string|max:255',
            'is_default'  => 'nullable|integer|in:0,1',
        ], [
            'name.required' => '收货人姓名不能为空',
            'name.max' => '收货人姓名不能超过64个字符',
            'mobile.required' => '请输入手机号',
            'mobile.regex' => '手机号格式错误',
            'province_id.required' => '请选择省份',
            'province_id.integer' => '省份ID必须为整数',
            'city_id.required' => '请选择城市',
            'city_id.integer' => '城市ID必须为整数',
            'district_id.required' => '请选择区县',
            'district_id.integer' => '区县ID必须为整数',
            'township_id.integer' => '乡镇/街道ID必须为整数',
            'address.required' => '详细地址不能为空',
            'address.max' => '详细地址不能超过255个字符',
            'is_default.in' => '是否默认地址只能为0或1',
        ]);

        return [
            'name'        => $data['name'],
            'mobile'      => $data['mobile'],
            'province_id' => (int) $data['province_id'],
            'city_id'     => (int) $data['city_id'],
            'district_id' => (int) $data['district_id'],
            'township_id' => (int) ($data['township_id'] ?? 0),
            'address'     => $data['address'],
            'is_default'  => (int) ($data['is_default'] ?? 0),
        ];
    }
}
