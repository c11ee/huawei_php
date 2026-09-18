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
        Schema::create('product_spu_category', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->bigInteger('category_id')->comment('分类 ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spu_category');
    }
};
