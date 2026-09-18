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
        Schema::create('product_brand', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('品牌名称');
            $table->string('logo')->default('')->comment('品牌logo');
            $table->integer('sort')->default(0)->comment('排序字段');
            $table->integer('status')->default(0)->comment('状态0-禁用1-启用');
            $table->timestamps();
            $table->comment('品牌表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_brand');
    }
};
