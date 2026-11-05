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
        Schema::create('app_cart_item', function (Blueprint $table) {
            $table->comment('购物车表');

            $table->id();
            $table->foreignId('user_id')->comment('用户ID')->constrained('app_user')->cascadeOnDelete();
            $table->unsignedBigInteger('sku_id')->comment('SKU ID');
            $table->unsignedBigInteger('quantity')->default(1)->comment('数量');
            $table->unsignedTinyInteger('is_checked')->default(0)->comment('是否选中 0: 否 1: 是');
            $table->json('snapshot_json')->nullable()->comment('商品快照(名称/图片/规格/品牌), 商品被删除后用于兜底展示');
            $table->timestamps();

            $table->unique(['user_id', 'sku_id'], 'uk_user_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_cart_item');
    }
};
