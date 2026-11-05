<?php

namespace App\Services;

use App\Models\Product\ProductDetail;
use App\Models\Product\ProductSku;
use App\Models\Product\ProductSpec;
use App\Models\Product\ProductSpecValue;
use App\Models\Product\ProductSpu;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * 获取商品详情（含关联）
     */
    public function getProductDetail(string $id): ?ProductSpu
    {
        // skus.specValues.spec 预加载规格链, 避免序列化时 sku_specs 追加属性逐 SKU 懒加载
        return ProductSpu::with('brand', 'creator', 'detail', 'updater', 'categories', 'specs', 'skus.specValues.spec')->find($id);
    }

    /**
     * 保存商品 (新增/编辑)
     */
    public function saveProduct(array $data, ?string $id = null): ProductSpu
    {
        return DB::transaction(function () use ($data, $id) {
            $userId = Auth::id() ?? 0;
            $spuId  = $id ?? null;
            $isEdit = !empty($spuId);

            // ==========================================
            // 1. 处理 SPU 主表
            // ==========================================
            $spuPayload = [
                'product_name'        => $data['spu']['product_name'],
                'product_short_name'  => $data['spu']['product_short_name'] ?? '',
                'product_description' => $data['spu']['product_description'] ?? '',
                'brand_id'            => $data['spu']['brand_id'] ?? 0,
                'spec_template_id'    => $data['spu']['spec_template_id'] ?? 0,
                'slider_images'       => $data['spu']['slider_images'] ?? [],
                'video_url'           => $data['spu']['video_url'] ?? '',
                'video_cover_url'     => $data['spu']['video_cover_url'] ?? '',
                'status'              => $data['spu']['status'] ?? 0,
                'sort'                => $data['spu']['sort'] ?? 0,
                'updated_by'          => $userId,
            ];

            if ($isEdit) {
                // 编辑时不传 spu_code，保留原编码
                $spu = ProductSpu::findOrFail($spuId);
                $spu->update($spuPayload);
            } else {
                $spuPayload['spu_code']               = $this->generateUniqueCode();
                $spuPayload['created_by']             = $userId;
                $spuPayload['default_sku_id']         = 0;
                $spuPayload['default_sku_sale_price'] = 0;
                $spuPayload['default_sku_stock']      = 0;
                $spuPayload['default_sku_image_url']  = '';
                $spuPayload['min_sale_price']         = 0;
                $spuPayload['max_sale_price']         = 0;
                $spuPayload['total_stock']            = 0;
                $spu                                  = ProductSpu::create($spuPayload);
            }

            // ==========================================
            // 2. 绑定分类关系
            // ==========================================
            if (isset($data['category_ids'])) {
                // 同步分类 覆盖关联关系
                $spu->categories()->sync($data['category_ids']);
            }

            // ==========================================
            // 3. 商品详情表 (product_detail)
            // ==========================================
            if (!empty($data['detail'])) {
                ProductDetail::updateOrCreate(
                    ['owner_type' => 'spu', 'owner_id' => $spu->id],
                    [
                        'detail_html'        => $data['detail']['detail_html'] ?? '',
                        'mobile_detail_html' => $data['detail']['mobile_detail_html'] ?? '',
                    ]
                );
            }

            // ==========================================
            // 4. 处理 规格项 (Spec) 与 规格值 (SpecValue)
            // ==========================================
            $specValueImageMap   = [];  // 真实规格值 ID => 规格值图片
            $specValueSpecIdMap  = [];  // 真实规格值 ID => 规格项 ID
            $specValueNameMap    = [];  // 真实规格值 ID => 规格值文本
            $specValueUidMap     = [];  // 临时规格值 ID => 真实规格值 ID(仅本次请求内有效)
            $submittedSpecIds    = [];  // 存储提交的规格项ID
            $submittedValueIds   = [];  // 存储提交的规格值ID

            // 本次提交中带真实 ID 的规格值, 新建查重时需排除, 避免把本次要更新的记录误判为重复
            $realValueIdsInSubmission = [];
            foreach ($data['specs'] ?? [] as $specItem) {
                foreach ($specItem['values'] ?? [] as $valItem) {
                    if ($this->isRealId($valItem['id'] ?? null)) {
                        $realValueIdsInSubmission[] = (int) $valItem['id'];
                    }
                }
            }

            if (!empty($data['specs'])) {
                foreach ($data['specs'] as $specItem) {
                    $specId  = $specItem['id'] ?? null;
                    $payload = [
                        'spu_id'            => $spu->id,
                        'name'              => $specItem['name'],
                        'is_image_required' => $specItem['is_image_required'] ?? 0,
                        'sort'              => $specItem['sort'] ?? 0,
                    ];

                    if ($this->isRealId($specId)) {
                        // 编辑已存在的规格: 必须属于当前商品, 防止跨商品改动
                        $spec = ProductSpec::where('id', (int) $specId)->where('spu_id', $spu->id)->first();
                        if (!$spec) {
                            throw new Exception("规格【{$specItem['name']}】不存在或不属于当前商品");
                        }
                        $spec->update($payload);
                    } else {
                        // 新增规格(临时 ID 或未传 ID)
                        $spec = ProductSpec::create($payload);
                    }
                    $submittedSpecIds[] = $spec->id;

                    foreach ($specItem['values'] ?? [] as $valItem) {
                        $valId      = $valItem['id'] ?? null;
                        $valPayload = [
                            'spu_id'    => $spu->id,
                            'spec_id'   => $spec->id,
                            'value'     => $valItem['value'],
                            'image_url' => $valItem['image_url'] ?? '',
                            'sort'      => $valItem['sort'] ?? 0,
                        ];

                        if ($this->isRealId($valId)) {
                            $specValue = ProductSpecValue::where('id', (int) $valId)->where('spu_id', $spu->id)->first();
                            if (!$specValue) {
                                throw new Exception("规格值【{$valItem['value']}】不存在或不属于当前商品");
                            }
                            $specValue->update($valPayload);
                        } else {
                            // 新建规格值前查重: 同一规格项下不允许出现重名值, 否则按 ID 绑定会产生歧义
                            $duplicate = ProductSpecValue::where('spu_id', $spu->id)
                                ->where('spec_id', $spec->id)
                                ->where('value', $valItem['value'])
                                ->whereNotIn('id', $realValueIdsInSubmission)
                                ->exists();
                            if ($duplicate) {
                                throw new Exception("规格值【{$specItem['name']}: {$valItem['value']}】已存在，请勿重复添加");
                            }

                            $specValue = ProductSpecValue::create($valPayload);
                        }

                        $submittedValueIds[]               = $specValue->id;
                        $specValueSpecIdMap[$specValue->id] = $spec->id;
                        $specValueNameMap[$specValue->id]   = $valItem['value'];

                        // 仅记录「需要图片」的规格项下的规格值图片，供 SKU 无图时兜底
                        if (!empty($specItem['is_image_required'])) {
                            $specValueImageMap[$specValue->id] = $valItem['image_url'] ?? '';
                        }

                        // 临时 ID 与真实 ID 的映射, 供本次请求内 SKU 绑定使用, 不落库
                        if ($valId !== null && $valId !== '' && $this->isTempId($valId)) {
                            $specValueUidMap[(string) $valId] = $specValue->id;
                        }
                    }
                }
            }

            // 编辑模式下删除移走的旧规格
            if ($isEdit) {
                ProductSpec::where('spu_id', $spu->id)->whereNotIn('id', $submittedSpecIds)->delete();
                ProductSpecValue::where('spu_id', $spu->id)->whereNotIn('id', $submittedValueIds)->delete();
            }

            // ==========================================
            // 5. 处理 SKU 及 中间表关联同步
            // ==========================================
            $submittedSkuIds = [];    // 存储提交的SKU ID
            $skuSpecImages   = [];    // 存储 SKU 对应的规格值图片：sku_id => image_url

            if (!empty($data['skus'])) {
                foreach ($data['skus'] as $skuItem) {
                    // SKU 的 id 也允许是临时 ID, 非真实 ID 一律按新增处理
                    $skuId = $this->isRealId($skuItem['id'] ?? null) ? (int) $skuItem['id'] : null;

                    // 1) 解析 SKU 绑定的规格值: 真实 ID 直接使用, 临时 ID 查本次请求内的映射
                    $syncPivotData = []; // 供 sync 写入中间表的数据, 格式: [spec_value_id => ['spu_id' => x, 'spec_id' => y]]
                    $realValueIds  = []; // 存储写入的 sku id

                    foreach ($skuItem['sku_spec'] ?? [] as $bound) {
                        $valueId = $bound['id'] ?? null;

                        if ($this->isRealId($valueId)) {
                            $realValueId = (int) $valueId;
                        } else {
                            $realValueId = $specValueUidMap[(string) $valueId] ?? null;
                        }

                        $specId = $realValueId ? ($specValueSpecIdMap[$realValueId] ?? null) : null;
                        if (!$specId) {
                            throw new Exception('SKU[' . ($skuItem['name'] ?? '') . "] 绑定的规格值 [{$valueId}] 不存在或不属于本次提交的规格");
                        }

                        // 前端传了规格值文本时校验一致性, 防止用陈旧数据完成绑定
                        if (!empty($bound['spec_value_name']) && ($specValueNameMap[$realValueId] ?? null) !== $bound['spec_value_name']) {
                            throw new Exception('SKU[' . ($skuItem['name'] ?? '') . "] 绑定的规格值【{$bound['spec_value_name']}】已变更，请刷新后重试");
                        }

                        $realValueIds[$realValueId]  = $realValueId;
                        $syncPivotData[$realValueId] = [
                            'spu_id'  => $spu->id,
                            'spec_id' => $specId,
                        ];
                    }

                    if ($realValueIds === []) {
                        throw new Exception('SKU[' . ($skuItem['name'] ?? '') . '] 未绑定任何规格值');
                    }
                    $realValueIds = array_values($realValueIds);

                    // 2) 解析规格值图片（SKU 自身无图时兜底）：map 中只会有「需要图片」规格项的图片
                    $specValueImage = '';
                    foreach ($realValueIds as $realValueId) {
                        if (!empty($specValueImageMap[$realValueId])) {
                            $specValueImage = $specValueImageMap[$realValueId];
                            break;
                        }
                    }

                    // 3) 计算规格组合 Hash 防重复
                    sort($realValueIds);
                    $specHash = hash('sha256', implode(',', $realValueIds));

                    // 4) 保存/更新 SKU 主表
                    $skuPayload = [
                        'spu_id'       => $spu->id,
                        'name'         => $skuItem['name'],
                        'is_default'   => $skuItem['is_default'] ?? 0,
                        'spec_hash'    => $specHash,
                        'image_url'    => $skuItem['image_url'] ?? '',
                        'sale_price'   => $skuItem['sale_price'] ?? 0,
                        'cost_price'   => $skuItem['cost_price'] ?? 0,
                        'strike_price' => $skuItem['strike_price'] ?? 0,
                        'stock'        => $skuItem['stock'],
                        'weight'       => $skuItem['weight'] ?? 0,
                        'volume'       => $skuItem['volume'] ?? 0,
                        'status'       => $skuItem['status'] ?? 1,
                        'sort'         => $skuItem['sort'] ?? 0,
                        'updated_by'   => $userId,
                        'created_by'   => $skuId ? DB::raw('created_by') : $userId,
                    ];

                    if (!$skuId) {
                        // 新增 SKU 生成唯一编码；编辑时不传 sku_code，保留原编码
                        $skuPayload['sku_code'] = $this->generateUniqueCode();
                    }

                    $sku = ProductSku::updateOrCreate(
                        ['id' => $skuId, 'spu_id' => $spu->id],
                        $skuPayload
                    );

                    $submittedSkuIds[] = $sku->id;
                    $skuSpecImages[$sku->id] = $specValueImage;

                    // 5) 同步更新 product_sku_spec_value 中间表
                    $sku->specValues()->sync($syncPivotData);
                }
            }

            // 编辑模式移除旧的 SKU
            if (!$isEdit) {
                ProductSku::where('spu_id', $spu->id)->whereNotIn('id', $submittedSkuIds)->delete();
            }

            // ==========================================
            // 6. 回写 SPU 聚合冗余统计字段
            // ==========================================
            $this->syncSpuAggregates((int) $spu->id, $skuSpecImages);

            return $spu->refresh()->load(['skus.specValues.spec', 'categories', 'detail', 'specs.values']);
        });
    }

    /**
     * 回写 SPU 聚合冗余字段：最低/最高售价、总库存、默认 SKU 及其价格、库存、主图
     *
     * 统一聚合入口：任何会改变 SKU 的场景（SKU 单独编辑、库存变动等）都应调用此方法，
     * 避免聚合字段与实际 SKU 数据不一致。
     *
     * @param  int        $spuId         SPU ID
     * @param  array|null $skuSpecImages SKU 对应的规格值图片 [sku_id => image_url]；
     *                                   传 null 表示不重算默认 SKU 主图，保留原值
     */
    public function syncSpuAggregates(int $spuId, ?array $skuSpecImages = null): void
    {
        $spu = ProductSpu::find($spuId);
        if (!$spu) {
            return;
        }

        // 与列表展示顺序保持一致：sort 升序，sort 相同按 id 升序
        $skus = ProductSku::where('spu_id', $spuId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'sale_price', 'stock', 'image_url', 'is_default', 'sort']);

        $prices = $skus->pluck('sale_price')->map(fn($price) => (float) $price)->all();

        // 优先取显式 is_default=1 的 SKU，没有则用排序后的第一个兜底
        $defaultSku = $skus->first(fn($sku) => (int) $sku->is_default === 1) ?: $skus->first();

        $payload = [
            'min_sale_price'         => $prices ? min($prices) : 0,
            'max_sale_price'         => $prices ? max($prices) : 0,
            'total_stock'            => (int) $skus->sum('stock'),
            // default_sku_id 列为 NOT NULL，无 SKU 时写 0 而不是 null
            'default_sku_id'         => $defaultSku ? (int) $defaultSku->id : 0,
            'default_sku_sale_price' => $defaultSku ? $defaultSku->sale_price : 0,
            'default_sku_stock'      => $defaultSku ? (int) $defaultSku->stock : 0,
        ];

        // 仅在显式传入规格值图片映射时重算主图，避免库存变更等场景把已有主图覆盖成空
        if ($skuSpecImages !== null) {
            $sliderImages = (array) $spu->slider_images;

            // 默认 SKU 主图优先级：SKU 自身图片 > 规格值图片 > SPU 轮播图首图
            $payload['default_sku_image_url'] = collect([
                $defaultSku?->image_url,
                $defaultSku ? ($skuSpecImages[$defaultSku->id] ?? '') : '',
                $sliderImages[0] ?? '',
            ])->filter()->first() ?? '';
        }

        $spu->update($payload);
    }

    /** 真实数据库 ID: 正整数, 允许 null(未传 ID 视为新增) */
    private function isRealId(int|string|null $id): bool
    {
        return (is_int($id) && $id > 0) || (is_string($id) && $id !== '' && ctype_digit($id) && (int) $id > 0);
    }

    /** 临时 ID: 非空字符串且含非数字字符 */
    private function isTempId(int|string|null $id): bool
    {
        return is_string($id) && $id !== '' && ! ctype_digit($id);
    }

    /**
     * 生成唯一商品编码：10086 + 9 位随机数字（共 14 位，如 10086133363559）
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = '10086' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        } while (
            ProductSpu::where('spu_code', $code)->exists()
            || ProductSku::where('sku_code', $code)->exists()
        );

        return $code;
    }
}
