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
        Schema::create('product_sku_spec_value', function (Blueprint $table) {
            $table->foreignId('spu_id')->constrained('product_spu')->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('product_sku')->cascadeOnDelete();
            $table->foreignId('spec_id')->constrained('product_spec')->cascadeOnDelete();
            $table->foreignId('spec_value_id')->constrained('product_spec_value')->cascadeOnDelete();

            // 联合主键: 防止同一个 SKU 重复绑定同一个规格值
            $table->primary(['sku_id', 'spec_value_id']);

            // 索引优化: 方便反查
            $table->index('spec_value_id'); // 核心: 根据规格值反查 SKU
            $table->index(['spu_id', 'spec_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sku_spec_value');
    }
};
