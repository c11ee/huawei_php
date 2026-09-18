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
        Schema::create('product_spec', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->string('name', 64)->comment('规格名称');
            $table->tinyInteger('is_image_required')->default(0)->comment('是否必填图片 0: 否 1: 是');
            // $table->tinyInteger('status')->default(0)->comment('状态 0禁用 1启用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spec');
    }
};
