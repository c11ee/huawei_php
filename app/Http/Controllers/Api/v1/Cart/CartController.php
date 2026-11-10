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
    /**
     * 获取购物车商品数量
     */
    public function count(Request $request)
    {
        $row = $request->user()->cartItems()
            ->selectRaw('COUNT(*) AS item_count, COALESCE(SUM(quantity), 0) AS total_quantity')
            ->first();
        return ApiResponse::success([
            'item_count'     => $row->item_count ?? 0,
            'total_quantity' => $row->total_quantity ?? 0,
        ]);
    }

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
            'sku_id'   => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sku = ProductSku::with(['spu.brand', 'specValues.spec'])->findOrFail($validated['sku_id']);
        $spu = $sku->spu;

        // $spu 取不到说明 SPU 已软删除或物理删除
        if ($sku->status !== 1 || !$spu || $spu->status !== 1) {
            return ApiResponse::error('商品已下架');
        }

        // // 可用库存 = 总库存 - 已锁定
        // $available = max(0, $sku->stock - $sku->locked_stock);
        // if ($available === 0) {
        //     return ApiResponse::error('库存不足');
        // }

        // 单次加购数量不能超过可用库存
        // $quantity = min($validated['quantity'], $available);
        $quantity = $validated['quantity'];

        $snapshot = $this->buildSnapshot($sku);

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
        // if ($cartItem->quantity >= $available) {
        //     return ApiResponse::error('库存不足，购物车已达该商品库存上限');
        // }

        // 已在购物车: 累加数量并刷新快照(商品删除后展示的是最新数据)
        $request->user()->cartItems()
            ->where('sku_id', $sku->id)
            ->update([
                'quantity'      => DB::raw('quantity + ' . (int) $quantity),
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
     * 切换规格
     * 目标 SKU 可能已在本用户购物车中, 此时不新增记录, 而是合并数量并删除当前项
     */
    public function updateSpec(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => ['required', 'integer'],
            'sku_id'  => ['required', 'integer'],
        ]);

        $user     = $request->user();
        $cartItem = $user->cartItems()->findOrFail($validated['cart_id']);

        // 规格未变化, 直接返回当前数量
        if ((int) $cartItem->sku_id === (int) $validated['sku_id']) {
            return ApiResponse::success([
                'cart_id'  => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'merged'   => false,
            ]);
        }

        $sku = ProductSku::with(['spu.brand', 'specValues.spec'])->find($validated['sku_id']);
        $spu = $sku?->spu;

        if (!$sku || !$spu) {
            return ApiResponse::error('商品不存在');
        }

        // 只能在同一个 SPU 内切换规格; SKU 已删除时用快照里的 spu_id 兜底
        $snapshotSpuId = (int) (($cartItem->snapshot_json ?? [])['spu_id'] ?? 0);
        $currentSpuId  = (int) ($cartItem->sku?->spu_id ?? $snapshotSpuId);
        if ($currentSpuId && (int) $sku->spu_id !== $currentSpuId) {
            return ApiResponse::error('只能切换到同一商品的其它规格');
        }

        // $spu 取不到说明 SPU 已删除; 与加购一致, 下架或删除都不可切换
        if ($sku->status !== 1 || $spu->status !== 1) {
            return ApiResponse::error('商品已下架');
        }

        // 可用库存 = 总库存 - 已锁定
        $available = max(0, $sku->stock - $sku->locked_stock);
        if ($available === 0) {
            return ApiResponse::error('库存不足');
        }

        $snapshot = $this->buildSnapshot($sku);

        [$targetId, $quantity, $merged] = DB::transaction(function () use ($user, $cartItem, $sku, $snapshot, $available) {
            // 目标规格已在购物车: 合并数量(不超过可用库存)后删除当前项
            $target = $user->cartItems()->where('sku_id', $sku->id)->lockForUpdate()->first();

            if ($target) {
                $quantity = min($target->quantity + $cartItem->quantity, $available);
                $target->update(['quantity' => $quantity, 'snapshot_json' => $snapshot]);
                $cartItem->delete();

                return [$target->id, $quantity, true];
            }

            $quantity = min($cartItem->quantity, $available);
            $cartItem->update([
                'sku_id'        => $sku->id,
                'quantity'      => $quantity,
                'snapshot_json' => $snapshot,
            ]);

            return [$cartItem->id, $quantity, false];
        });

        return ApiResponse::success([
            'cart_id'  => $targetId,
            'quantity' => $quantity,
            'merged'   => $merged,
        ]);
    }

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

    /** 构建购物车商品快照, 商品被删除后用于兜底展示 */
    private function buildSnapshot(ProductSku $sku): array
    {
        $spu = $sku->spu;

        return [
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
    }
}
