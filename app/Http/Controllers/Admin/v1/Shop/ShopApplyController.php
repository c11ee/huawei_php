<?php

namespace App\Http\Controllers\Api\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Shop\Shop;
use App\Models\Shop\ShopAdmin;
use App\Models\Shop\ShopApply;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /** 列表 */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $status = $request->input('status', 0);
        $keyword = $request->input('keyword', '');

        $query = ShopApply::query()->where('status', $status);

        if ($keyword) {
            $query->where('shop_name', 'like', "%{$keyword}%")
                ->orWhere('contact_name', 'like', "%{$keyword}%")
                ->orWhere('contact_phone', 'like', "%{$keyword}%")
                ->orWhere('audit_remark', 'like', "%{$keyword}%");
        }

        // 分页
        $shopApplies = $query->paginate($limit);

        return ApiResponse::success([
            'data'  => $shopApplies->items(),
            'page'  => $shopApplies->currentPage(),
            'total' => $shopApplies->total(),
        ]);
    }

    /** 提交申请 */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        $user = $request->user();

        // 是否已提交申请
        $shopApply = ShopApply::where('user_id', $user->id)->where('status', 0)->first();
        if ($shopApply) {
            return ApiResponse::error('您已提交申请，无需重复提交');
        }

        // 提交申请
        $shopApply = ShopApply::create(array_merge(
            $validated,
            [
                'user_id' => $user->id,
                'status'  => 0,
            ]
        ));

        return ApiResponse::success($shopApply);
    }

    /** 获取用户在审核的店铺申请 */
    public function show(Request $request)
    {
        $user = $request->user();
        $shopApply = ShopApply::where('user_id', $user->id)->where('status', 0)->first();
        if (!$shopApply) {
            return ApiResponse::error('您未提交申请');
        }

        return ApiResponse::success($shopApply);
    }

    /** 编辑申请 (驳回用户再次提交申请, 还是通个申请) */
    public function update(Request $request)
    {
        $validated = $this->validateData($request);
        $shopApply = ShopApply::where('id', $request->input('id'))->first();

        if (!$shopApply) {
            return ApiResponse::error('申请不存在');
        }

        $shopApply->update(array_merge(
            $validated,
            [
                'status' => 0,
            ]
        ));

        $shopApply->save();

        return ApiResponse::success($shopApply);
    }

    /** 审核申请 */
    public function audit(Request $request)
    {
        $validated = $request->request([
            'id'           => 'required|integer',
            'status'       => 'required|integer|in:1,2',
            'audit_remark' => 'required|string|max:255',
        ]);

        $shopApply = ShopApply::find($validated['id']);
        if (!$shopApply) {
            return ApiResponse::error('申请不存在');
        }

        // 需要判断驳回时将 店铺还有店铺账号 禁用
        // 如果重复审核通过需提示, 数据已存在
        // 通过时, 需要在 店铺,店铺账号 增加一条数据

        $shopApply->update([
            'status'       => $validated['status'],
            'audit_remark' => $validated['audit_remark'],
        ]);

        $shopApply->save();

        if ($shopApply->status === 1) {
        }

        return ApiResponse::success($shopApply);
    }

    public function validateData(Request $request)
    {
        $data =  $request->validate([], []);

        return [];
    }
}
