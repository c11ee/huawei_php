<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_spu', function (Blueprint $table) {
            $table->id();
            $table->string('spu_code', 64)->comment('SPU编码');
            $table->string('product_name', 128)->comment('产品名称');
            $table->integer('brand_id')->comment('品牌ID');
            $table->integer('spec_template_id')->comment('规格模板ID');
            $table->integer('default_sku_id')->comment('默认SKU ID');
            $table->decimal('default_sku_sale_price', 12, 2)->comment('默认SKU销售价格');
            $table->integer('default_sku_stock')->comment('默认SKU库存');
            $table->decimal('min_sale_price', 12, 2)->comment('最小销售价格');
            $table->decimal('max_sale_price', 12, 2)->comment('最大销售价格');
            $table->integer('total_stock')->comment('总库存');
            $table->tinyInteger('status')->default(0)->comment('状态 0: 草稿 1: 上架 2: 下架');
            $table->integer('sort')->default(0)->comment('排序');
            $table->integer('created_by')->comment('创建人');
            $table->integer('updated_by')->comment('更新人');
            // 软删除
            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spu');
    }
};
