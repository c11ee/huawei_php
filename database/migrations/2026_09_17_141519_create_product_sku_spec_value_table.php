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
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->bigInteger('sku_id')->comment('SKU ID');
            $table->bigInteger('spec_id')->comment('规格 ID');
            $table->bigInteger('spec_value_id')->comment('规格值 ID');
            $table->timestamps();
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
