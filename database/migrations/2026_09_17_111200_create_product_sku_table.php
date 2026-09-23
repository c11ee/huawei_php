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
        Schema::create('product_sku', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->string('sku_code', 64)->comment('SKU编码');
            $table->string('name', 64)->comment('SKU名称');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认');
            $table->char('spec_hash', 64)->comment('规格组合 SHA-256 防重复');
            $table->json('spec_json')->nullable()->comment('规格快照');
            $table->string('image_url', 255)->comment('SKU主图');
            $table->decimal('sale_price', 12, 2)->default(0.00)->comment('销售价格');
            $table->decimal('cost_price', 12, 2)->default(0.00)->comment('成本价格');
            $table->decimal('strike_price', 12, 2)->default(0.00)->comment('划线价格');
            $table->integer('stock')->default(0)->comment('库存');
            $table->integer('locked_stock')->default(0)->comment('锁定库存');
            $table->decimal('weight', 10, 3)->default(0.00)->comment('重量, kg');
            $table->decimal('volume', 12, 4)->default(0.00)->comment('体积, m³');
            $table->tinyInteger('status')->default(0)->comment('状态 0禁用 1启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->bigInteger('created_by')->comment('创建人');
            $table->bigInteger('updated_by')->comment('更新人');
            $table->timestamps();

            $table->unique(['spu_id', 'spec_hash'], 'uk_spu_spec_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sku');
    }
};
