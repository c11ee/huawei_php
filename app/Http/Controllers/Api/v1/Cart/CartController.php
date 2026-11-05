<?php

namespace App\Http\Controllers\Api\v1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product\ProductSku;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request)
    {
        // 预加载 SKU 规格与品牌，避免逐项懒加载；SPU 带软删除，保证商品删除后购物车仍能展示
        $cartItems = $request->user()->cartItems()
            ->with([
                'sku.specValues.spec',
                'sku.spu' => fn($q) => $q->withTrashed()->with('brand'),
            ])
            ->latest('id')
            ->get();

        // dd($cartItems->toArray());
        // 每项只执行一次 product 访问器，避免分组与序列化重复计算
        $rows = $cartItems->map(fn($item) => [
            'item'    => $item->makeHidden(['sku', 'snapshot_json'])->toArray(),
            'product' => $item->product,
        ]);

        $groups = $rows
            ->groupBy(fn($row) => $row['product']['brand_id'])
            ->map(function ($rows, $brandId) {
                $first = $rows->first()['product'];

                return [
                    'brand_id'   => (int) $brandId,
                    'brand_name' => $first['brand_name'],
                    'brand_logo' => $first['brand_logo'],
                    'item_count' => $rows->count(),
                    'items'      => $rows
                        ->map(fn($row) => $row['item'] + ['product' => $row['product']])
                        ->values()
                ];
            })
            ->values();

        return ApiResponse::success($groups);
    }

    /**
     * 添加购物车
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku_id' => ['required', 'integer'],
            'num' => ['required', 'integer', 'min:1'],
        ]);

        $sku = ProductSku::with(['spu.brand', 'specValues.spec'])->findOrFail($validated['sku_id']);
        $spu = $sku->spu;

        // $spu 取不到说明 SPU 已软删除或物理删除
        if ($sku->status !== 1 || !$spu || $spu->status !== 1) {
            return ApiResponse::error('商品已下架');
        }

        // 可用库存 = 总库存 - 已锁定
        $available = max(0, $sku->stock - $sku->locked_stock);
        if ($available === 0) {
            return ApiResponse::error('库存不足');
        }

        // 单次加购数量不能超过可用库存
        $quantity = min($validated['num'], $available);

        $snapshot = [
            'spu_id'     => $sku->spu_id,
            'spu_name'   => $spu->product_name ?? '',
            'sku_name'   => $sku->name,
            'image_url'  => $sku->image_url ?: ($spu->default_sku_image_url ?? ''),
            'sale_price' => $sku->sale_price,
            'sku_specs'  => $sku->sku_specs,
            'brand_id'   => $spu->brand_id ?? 0,
            'brand_name' => $spu->brand?->name ?? '',
            'brand_logo' => $spu->brand?->logo ?? '',
            'stock'      => $sku->stock,
        ];

        $cartItem = $request->user()->cartItems()->where('sku_id', $sku->id)->first();

        if (!$cartItem) {
            try {
                // user_id 由 hasMany 关联自动填充
                $request->user()->cartItems()->create([
                    'sku_id'        => $sku->id,
                    'quantity'      => $quantity,
                    'snapshot_json' => $snapshot,
                ]);

                return ApiResponse::success([]);
            } catch (QueryException $e) {
                // 并发首次加购命中唯一索引 uk_user_sku: 记录已被另一请求创建, 转为下面的累加
                $cartItem = $request->user()->cartItems()->where('sku_id', $sku->id)->first();

                if (!$cartItem) {
                    throw $e;
                }
            }
        }

        // 已达可用库存时提示用户, 否则直接累加(超出部分由 LEAST 截断)
        if ($cartItem->quantity >= $available) {
            return ApiResponse::error('库存不足，购物车已达该商品库存上限');
        }

        // 累加在数据库内完成, LEAST 兜底防止并发下超出可用库存; 同时刷新快照, 保证商品删除后回退展示的是最新数据
        $request->user()->cartItems()
            ->where('sku_id', $sku->id)
            ->update([
                'quantity'      => DB::raw(sprintf('LEAST(quantity + %d, %d)', $quantity, $available)),
                // Eloquent 的批量 update 不走模型 cast, 需手动转 JSON
                'snapshot_json' => json_encode($snapshot),
            ]);

        return ApiResponse::success([]);
    }

    /** 切换选中状态 */
    public function updateChecked(Request $request)
    {
        $validated = $request->validate([
            'is_checked' => ['required', 'integer', 'in:0,1'],
            'ids'        => ['required', 'string'],
        ]);

        $ids = $this->parseIds($validated['ids']);
        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }

        $request->user()->cartItems()->whereIn('id', $ids)->update(['is_checked' => $validated['is_checked']]);

        return ApiResponse::success([]);
    }

    /** 编辑数量 */
    public function updateQuantity(Request $request, int $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $cartItem = $request->user()->cartItems()->with(['sku'])->findOrFail($id);
        $sku = $cartItem->sku;
        $spu = $sku->spu;

        if (!$sku) {
            return ApiResponse::error('商品不存在');
        }
        // $spu 取不到说明 SPU 已软删除或物理删除
        if ($sku->status !== 1 || !$spu || $spu->status !== 1) {
            return ApiResponse::error('商品已下架');
        }

        // 可用库存 = 总库存 - 已锁定
        $available = max(0, $sku->stock - $sku->locked_stock);
        if ($available === 0) {
            return ApiResponse::error('库存不足');
        }

        // 超出可用库存时截断, 数量未变化则不写库
        $quantity = min($validated['quantity'], $available);
        if ($quantity === $cartItem->quantity) {
            return ApiResponse::success([]);
        }

        $cartItem->update(['quantity' => $quantity]);
        return ApiResponse::success([]);
    }

    /** 
     * 切换规格, 需要注意,可能切换为已添加购物车的 sku, 这时需要更新购物车数量
     */

    /** 删除购物车 */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'string'],
        ]);
        $ids = $this->parseIds($validated['ids']);
        if ($ids === []) {
            return ApiResponse::error('参数错误');
        }
        $request->user()->cartItems()->whereIn('id', $ids)->delete();

        return ApiResponse::success([]);
    }
}
