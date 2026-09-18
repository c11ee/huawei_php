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
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->tinyInteger('owner_type')->comment('所属类型 1 SPU, 2 规格项, 3 规格值, 4 SKU');
            $table->bigInteger('owner_id')->comment('所属ID');
            $table->tinyInteger('media_type')->comment('媒体类型 1 主图, 2 轮播图, 3 视频, 4 详情图');
            $table->json('media_json')->nullable()->comment('媒体JSON');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
