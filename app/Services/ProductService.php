<?php

namespace app\Services;

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
     * 保存商品 (新增/编辑)
     */
    public function saveProduct(array $data, ?string $id = null): ProductSpu
    {
        return DB::transaction(function () use ($data, $id) {
            // $userId = Auth::id() ?? 0;
            $userId = 7;
            $spuId  = $id ?? null;
            $isEdit = !empty($spuId);

            // ==========================================
            // 1. 处理 SPU 主表
            // ==========================================
            $spuPayload = [
                'product_name'        => $data['spu']['product_name'],
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
            $specValueMap      = [];  // 存储映射关系：'颜色|星云灰' => ['spec_id' => X, 'spec_value_id' => Y]
            $specValueImageMap = [];  // 存储规格值图片：spec_value_id => image_url
            $submittedSpecIds  = [];  // 存储提交的规格项ID
            $submittedValueIds = [];  // 存储提交的规格值ID

            if (!empty($data['specs'])) {
                foreach ($data['specs'] as $specItem) {
                    $specId = $specItem['id'] ?? null;

                    $spec = ProductSpec::updateOrCreate(
                        ['id' => $specId, 'spu_id' => $spu->id],
                        [
                            'spu_id'            => $spu->id,
                            'name'              => $specItem['name'],
                            'is_image_required' => $specItem['is_image_required'] ?? 0,
                            'sort'              => $specItem['sort'] ?? 0,
                        ]
                    );
                    $submittedSpecIds[] = $spec->id;

                    if (!empty($specItem['values'])) {
                        foreach ($specItem['values'] as $valItem) {
                            $valId = $valItem['id'] ?? null;

                            $specValue = ProductSpecValue::updateOrCreate(
                                ['id' => $valId, 'spu_id' => $spu->id],
                                [
                                    'spu_id'    => $spu->id,
                                    'spec_id'   => $spec->id,
                                    'value'     => $valItem['value'],
                                    'image_url' => $valItem['image_url'] ?? '',
                                    'sort'      => $valItem['sort'] ?? 0,
                                ]
                            );
                            $submittedValueIds[] = $specValue->id;

                            // 仅记录「需要图片」的规格项下的规格值图片，供 SKU 无图时兜底
                            if (!empty($specItem['is_image_required'])) {
                                $specValueImageMap[$specValue->id] = $valItem['image_url'] ?? '';
                            }

                            // 逻辑 Key 绑定
                            $key = $specItem['name'] . '|' . $valItem['value'];
                            $specValueMap[$key] = [
                                'spec_id'       => $spec->id,
                                'spec_value_id' => $specValue->id,
                            ];
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
            $prices          = [];    // 存储提交的价格
            $totalStock      = 0;     // 存储总库存
            $defaultSku      = null;  // 显式 is_default=1 的 SKU（只取第一个）
            $firstSku        = null;  // 首个 SKU，无显式默认时兜底

            if (!empty($data['skus'])) {
                foreach ($data['skus'] as $skuItem) {
                    $skuId = $skuItem['id'] ?? null;

                    // 1) 依据 spec_values: {"颜色":"星云灰", ...} 解析真实的数据库 ID
                    $syncPivotData = []; // 供 sync 写入中间表的数据, 格式: [spec_value_id => ['spu_id' => x, 'spec_id' => y]]
                    $realValueIds  = []; // 存储写入的 sku id

                    if (!empty($skuItem['spec_values'])) {
                        foreach ($skuItem['spec_values'] as $specNameText => $specValueText) {
                            $mapKey = $specNameText . '|' . $specValueText;
                            if (isset($specValueMap[$mapKey])) {
                                $specId = $specValueMap[$mapKey]['spec_id'];
                                $specValueId = $specValueMap[$mapKey]['spec_value_id'];

                                $realValueIds[] = $specValueId;
                                $syncPivotData[$specValueId] = [
                                    'spu_id' => $spu->id,
                                    'spec_id' => $specId,
                                ];
                            } else {
                                throw new Exception("规格值 {$specNameText} | {$specValueText} 不存在");
                            }
                        }
                    }

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
                        'spec_json'    => $skuItem['spec_values'],                    // 存入 JSON 快照
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

                    $prices[]    = $sku->sale_price;
                    $totalStock += $sku->stock;

                    // 记录默认 SKU：优先第一个显式 is_default=1 的，避免后面的 SKU 覆盖
                    if ($firstSku === null) {
                        $firstSku = $sku;
                    }
                    if ($defaultSku === null && !empty($skuItem['is_default'])) {
                        $defaultSku = $sku;
                    }
                }
            }

            // 编辑模式移除旧的 SKU
            if (!$isEdit) {
                ProductSku::where('spu_id', $spu->id)->whereNotIn('id', $submittedSkuIds)->delete();
            }

            // ==========================================
            // 6. 回写 SPU 聚合冗余统计字段
            // ==========================================
            // 若没有任何 SKU 标记 is_default，则退回使用第一个 SKU
            $defaultSku = $defaultSku ?: $firstSku;

            $sliderImages    = array_values($data['spu']['slider_images'] ?? []);
            // 默认 SKU 图片优先级：SKU 自身图片 > 规格值图片 > SPU 轮播图首图
            $defaultSkuImage = collect([
                $defaultSku?->image_url,
                $skuSpecImages[$defaultSku?->id] ?? '',
                $sliderImages[0] ?? '',
            ])->filter()->first() ?? '';
            $spu->update([
                'min_sale_price'         => count($prices) ? min($prices) : 0,
                'max_sale_price'         => count($prices) ? max($prices) : 0,
                'total_stock'            => $totalStock,
                'default_sku_id'         => $defaultSku ? $defaultSku->id : null,
                'default_sku_sale_price' => $defaultSku ? $defaultSku->sale_price : 0,
                'default_sku_stock'      => $defaultSku ? $defaultSku->stock : 0,
                'default_sku_image_url'  => $defaultSkuImage,
            ]);

            return $spu->load(['skus.specValues', 'categories', 'detail', 'specs.values']);
        });
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
