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
            'spu.product_name'        => ['required', 'string', 'max:255'],
            'spu.product_description' => ['nullable', 'string', 'max:1000'],
            'spu.brand_id'            => ['nullable', 'integer', 'min:0'],
            'spu.spec_template_id'    => ['nullable', 'integer', 'min:0'],
            'spu.slider_images'       => ['required', 'array', 'min:1'],
            'spu.slider_images.*'     => ['required', 'url'],
            'spu.video_url'           => ['nullable', 'string'],
            'spu.video_cover_url'     => ['nullable', 'string'],
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
            'specs'                   => ['required', 'array', 'min:1'],
            'specs.*.id'              => ['nullable', 'integer', 'exists:product_spec,id'],
            'specs.*.name'            => ['required', 'string', 'distinct', 'max:64'],
            'specs.*.is_image_required' => ['required', 'in:0,1'],
            'specs.*.sort'            => ['nullable', 'integer', 'min:0'],
            'specs.*.values'          => ['required', 'array', 'min:1'],
            'specs.*.values.*.id'     => ['nullable', 'integer', 'exists:product_spec_value,id'],
            'specs.*.values.*.value'  => ['required', 'string', 'max:64'],
            'specs.*.values.*.image_url' => ['nullable', 'string'],
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
            'skus.*.name'             => ['required', 'string', 'max:255'],
            'skus.*.image_url'        => ['nullable', 'string'],
            'skus.*.sale_price'       => ['required', 'numeric', 'min:0.01'],
            'skus.*.cost_price'       => ['nullable', 'numeric', 'min:0'],
            'skus.*.strike_price'     => ['nullable', 'numeric', 'min:0'],
            'skus.*.stock'            => ['required', 'integer', 'min:0'],
            'skus.*.weight'           => ['nullable', 'numeric', 'min:0'],
            'skus.*.volume'           => ['nullable', 'numeric', 'min:0'],
            'skus.*.status'           => ['required', 'in:0,1'],
            'skus.*.sort'             => ['nullable', 'integer', 'min:0'],
            'skus.*.spec_values'      => ['required', 'array'],
        ];
    }

    /**
     * 高级关联校验：验证 SKU 中的 spec_values 与传入的 specs 规则定义是否一致
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $specs = $this->input('specs', []);
            $skus  = $this->input('skus', []);

            if (empty($specs) || empty($skus)) {
                return;
            }

            // 提取所有合法规格名及对应的规格值列表
            // 格式: ['颜色' => ['星云灰', '雪地白'], '版本' => ['12GB+256GB']]
            $validSpecMap = [];
            foreach ($specs as $spec) {
                if (!empty($spec['name']) && !empty($spec['values'])) {
                    $validSpecMap[$spec['name']] = array_column($spec['values'], 'value');
                }
            }

            $requiredSpecNames = array_keys($validSpecMap);
            sort($requiredSpecNames);

            foreach ($skus as $index => $sku) {
                $skuSpecValues = $sku['spec_values'] ?? [];
                $currentSpecNames = array_keys($skuSpecValues);
                sort($currentSpecNames);

                // 1. 检查 SKU 是否漏掉了某些规格项，或者多了未定义的规格项
                if ($requiredSpecNames !== $currentSpecNames) {
                    $validator->errors()->add(
                        "skus.{$index}.spec_values",
                        "SKU[{$sku['name']}] 的规格项组合与定义的规格不匹配，必须包含所有且仅包含: " . implode(', ', $requiredSpecNames)
                    );
                    continue;
                }

                // 2. 检查 SKU 的规格值是否在 specs.values 的可选项列表内
                foreach ($skuSpecValues as $specName => $specVal) {
                    if (!in_array($specVal, $validSpecMap[$specName] ?? [])) {
                        $validator->errors()->add(
                            "skus.{$index}.spec_values.{$specName}",
                            "SKU[{$sku['name']}] 的【{$specName}】规格值「{$specVal}」不在定义的规格值列表中！"
                        );
                    }
                }
            }
        });
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
            'skus.*.spec_values.required'   => 'SKU 规格属性配置不能为空',
        ];
    }
}
