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
        // 一个 SKU 一天一行
        Schema::create('stat_daily', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->comment('统计日期');
            $table->bigInteger('sku_id')->comment('SKU id');
            $table->unsignedBigInteger('pv')->default(0)->comment('浏览量');
            $table->unsignedBigInteger('sales_qty')->default(0)->comment('销量(件)');
            $table->unsignedBigInteger('order_count')->default(0)->comment('下单笔数');
            $table->timestamps();

            $table->unique(['stat_date', 'sku_id'], 'uk_date_sku');
            $table->index(['sku_id', 'stat_date'], 'idx_sku_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stat_daily');
    }
};
