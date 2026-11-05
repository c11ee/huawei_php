<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    /**
     * 授权验证
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 规则定义
     */
    public function rules(): array
    {
        return [
            // ==========================================
            // 1. SPU 规则校验
            // ==========================================
            'spu'                       => ['required', 'array'],
            'spu.id'                    => ['nullable', 'integer', 'exists:product_spu,id'],
            // 'spu.spu_code'              => [
            //     'required',
            //     'string',
            //     'max:64',
            //     // 编辑模式时排除自身唯一性冲突
            //     Rule::unique('product_spu', 'spu_code')->ignore($this->input('spu.id'))
            // ],
            // 长度上限与数据库列定义保持一致, 避免通过校验后在写库时抛 SQL 错误
            'spu.product_name'        => ['required', 'string', 'max:128'],
            'spu.product_short_name'  => ['nullable', 'string', 'max:128'],
            'spu.product_description' => ['nullable', 'string', 'max:255'],
            'spu.brand_id'            => ['nullable', 'integer', 'min:0'],
            'spu.spec_template_id'    => ['nullable', 'integer', 'min:0'],
            'spu.slider_images'       => ['required', 'array', 'min:1'],
            'spu.slider_images.*'     => ['required', 'url', 'max:255'],
            'spu.video_url'           => ['nullable', 'string', 'max:255'],
            'spu.video_cover_url'     => ['nullable', 'string', 'max:255'],
            'spu.status'              => ['required', 'in:0,1,2'],
            'spu.sort'                => ['nullable', 'integer', 'min:0'],

            // ==========================================
            // 2. 分类与详情规则
            // ==========================================
            'category_ids'            => ['required', 'array', 'min:1'],
            'category_ids.*'          => ['required', 'integer', 'exists:product_category,id'],

            'detail'                  => ['nullable', 'array'],
            'detail.detail_html'        => ['nullable', 'string'],
            'detail.mobile_detail_html' => ['nullable', 'string'],

            // ==========================================
            // 3. 规格与规格值规则
            // ==========================================
            // id: 已存在记录传真实数据库 ID(纯数字), 新增记录传前端临时 ID(含非数字字符)
            // 格式与唯一性由 withValidator 统一校验, 归属校验在 Service 内完成
            'specs'                   => ['required', 'array', 'min:1'],
            'specs.*.id'              => ['nullable'],
            'specs.*.name'            => ['required', 'string', 'distinct', 'max:64'],
            'specs.*.is_image_required' => ['required', 'in:0,1'],
            'specs.*.sort'            => ['nullable', 'integer', 'min:0'],
            'specs.*.values'          => ['required', 'array', 'min:1'],
            'specs.*.values.*.id'     => ['nullable'],
            'specs.*.values.*.value'  => ['required', 'string', 'max:64'],
            'specs.*.values.*.image_url' => ['nullable', 'string', 'max:255'],
            'specs.*.values.*.sort'   => ['nullable', 'integer', 'min:0'],

            // ==========================================
            // 4. SKU 规则
            // ==========================================
            'skus'                    => ['required', 'array', 'min:1'],
            'skus.*.id'               => ['nullable', 'integer', 'exists:product_sku,id'],
            'skus.*.is_default'       => ['required', 'in:0,1'],
            // 'skus.*.sku_code'         => [
            //     'required',
            //     'string',
            //     'distinct',
            //     'max:64',
            // ],
            'skus.*.name'             => ['required', 'string', 'max:64'],
            'skus.*.image_url'        => ['nullable', 'string', 'max:255'],
            'skus.*.sale_price'       => ['required', 'numeric', 'min:0.01'],
            'skus.*.cost_price'       => ['nullable', 'numeric', 'min:0'],
            'skus.*.strike_price'     => ['nullable', 'numeric', 'min:0'],
            'skus.*.stock'            => ['required', 'integer', 'min:0'],
            'skus.*.weight'           => ['nullable', 'numeric', 'min:0'],
            'skus.*.volume'           => ['nullable', 'numeric', 'min:0'],
            'skus.*.status'           => ['required', 'in:0,1'],
            'skus.*.sort'             => ['nullable', 'integer', 'min:0'],
            // 规格值绑定: 每项 id 为真实 ID 或临时 ID, spec_value_name 可选(传了则做一致性校验)
            'skus.*.sku_spec'         => ['required', 'array', 'min:1'],
            'skus.*.sku_spec.*.id'    => ['required'],
            'skus.*.sku_spec.*.spec_value_name' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * 高级关联校验
     * 核心规则: SKU 绑定的规格值必须出现在本次提交的 specs[].values 中, 无论它是真实 ID 还是临时 ID。
     * 因此整个校验只依赖请求体自身, 不需要查询数据库。
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $specs = $this->input('specs', []);
            $skus  = $this->input('skus', []);

            if (empty($specs) || empty($skus)) {
                return;
            }

            // 本次提交的规格值 ID(统一转字符串) => 所属规格项名称
            $valueIdToSpecName = [];
            $seenSpecIds       = []; // 临时规格 ID 去重
            $seenValueIds      = []; // 临时规格值 ID 去重
            $requiredSpecNames = [];

            foreach ($specs as $i => $spec) {
                $specName = $spec['name'] ?? '';
                if ($specName !== '') {
                    $requiredSpecNames[] = $specName;
                }

                $specId = $spec['id'] ?? null;
                if ($specId !== null && $specId !== '') {
                    if ($this->isRealId($specId)) {
                        // 真实 ID 的归属校验在 Service 内完成
                    } elseif ($this->isTempId($specId)) {
                        if (isset($seenSpecIds[$specId])) {
                            $validator->errors()->add("specs.{$i}.id", "规格【{$specName}】的临时 ID [{$specId}] 重复");
                        }
                        $seenSpecIds[$specId] = true;
                    } else {
                        $validator->errors()->add("specs.{$i}.id", "规格【{$specName}】的 ID 格式非法，必须为整数或含字母的临时 ID");
                    }
                }

                foreach ($spec['values'] ?? [] as $j => $value) {
                    $valueId    = $value['id'] ?? null;
                    $valueLabel = $value['value'] ?? '';

                    if ($valueId === null || $valueId === '') {
                        continue;
                    }

                    if ($this->isTempId($valueId)) {
                        if (isset($seenValueIds[$valueId])) {
                            $validator->errors()->add("specs.{$i}.values.{$j}.id", "规格值【{$valueLabel}】的临时 ID [{$valueId}] 重复");
                        }
                        $seenValueIds[$valueId] = true;
                    } elseif (! $this->isRealId($valueId)) {
                        $validator->errors()->add("specs.{$i}.values.{$j}.id", "规格值【{$valueLabel}】的 ID 格式非法，必须为整数或含字母的临时 ID");
                        continue;
                    }

                    $valueIdToSpecName[(string) $valueId] = $specName;
                }
            }

            sort($requiredSpecNames);

            foreach ($skus as $index => $sku) {
                $skuName = $sku['name'] ?? '';
                $skuSpec = $sku['sku_spec'] ?? [];

                if ($skuSpec === []) {
                    $validator->errors()->add("skus.{$index}.sku_spec", "SKU[{$skuName}] 未绑定规格值");
                    continue;
                }

                $boundNames = [];  // 已绑定的规格项名称
                $hasError   = false;

                foreach ($skuSpec as $k => $bound) {
                    $valueId = $bound['id'] ?? null;

                    if ($valueId === null || $valueId === '' || (! $this->isRealId($valueId) && ! $this->isTempId($valueId))) {
                        $validator->errors()->add("skus.{$index}.sku_spec.{$k}.id", "SKU[{$skuName}] 存在非法或为空的规格值 ID");
                        $hasError = true;
                        continue;
                    }

                    $specName = $valueIdToSpecName[(string) $valueId] ?? null;
                    if ($specName === null) {
                        $validator->errors()->add(
                            "skus.{$index}.sku_spec.{$k}.id",
                            "SKU[{$skuName}] 绑定的规格值 [{$valueId}] 不在本次提交的 specs 中"
                        );
                        $hasError = true;
                        continue;
                    }

                    if (isset($boundNames[$specName])) {
                        $validator->errors()->add(
                            "skus.{$index}.sku_spec.{$k}.id",
                            "SKU[{$skuName}] 的【{$specName}】重复绑定，同一规格项只能绑定一个规格值"
                        );
                        $hasError = true;
                        continue;
                    }

                    $boundNames[$specName] = true;
                }

                if ($hasError) {
                    continue;
                }

                // 合并后必须覆盖所有规格项, 且不能多出未定义的规格项
                $currentSpecNames = array_keys($boundNames);
                sort($currentSpecNames);
                if ($requiredSpecNames !== $currentSpecNames) {
                    $validator->errors()->add(
                        "skus.{$index}.sku_spec",
                        "SKU[{$skuName}] 的规格项组合与定义的规格不匹配，必须包含所有且仅包含: " . implode(', ', $requiredSpecNames)
                    );
                }
            }
        });
    }

    /** 真实数据库 ID: 正整数 */
    private function isRealId(string|int $id): bool
    {
        return (is_int($id) && $id > 0) || (is_string($id) && $id !== '' && ctype_digit($id) && (int) $id > 0);
    }

    /** 临时 ID: 非空字符串且含非数字字符 */
    private function isTempId(string $id): bool
    {
        return is_string($id) && $id !== '' && ! ctype_digit($id);
    }

    /**
     * 自定义错误提示信息
     */
    public function messages(): array
    {
        return [
            'spu.required'                  => 'SPU 信息不能为空',
            // 'spu.spu_code.required'         => '请输入 SPU 编码',
            // 'spu.spu_code.unique'           => 'SPU 编码已被使用',
            'spu.product_name.required'     => '请输入商品名称',
            'spu.slider_images.required'    => '请上传至少一张商品轮播图',

            'category_ids.required'         => '请选择商品分类',
            'category_ids.*.exists'         => '选中的某个分类不存在',

            'specs.required'                => '请配置商品规格',
            'specs.*.name.required'         => '规格名称不能为空',
            'specs.*.name.distinct'         => '规格名称不能重复',
            'specs.*.values.required'       => '每个规格项下必须至少有一个规格值',
            'specs.*.values.*.value.required' => '规格值文本不能为空',

            'skus.required'                 => '至少需要包含一个 SKU',
            // 'skus.*.sku_code.required'      => 'SKU 编码不能为空',
            // 'skus.*.sku_code.distinct'      => '提交的 SKU 编码列表中存在重复值',
            'skus.*.sale_price.required'    => 'SKU 销售价不能为空',
            'skus.*.sale_price.min'         => 'SKU 销售价必须大于 0',
            'skus.*.stock.required'         => 'SKU 库存不能为空',
            'skus.*.sku_spec.required'       => 'SKU 规格属性配置不能为空',
            'skus.*.sku_spec.*.id.required'  => 'SKU 规格属性配置不能为空',
        ];
    }
}
