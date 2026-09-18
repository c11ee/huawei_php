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
        Schema::create('product_spec_value', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spu_id')->comment('SPU ID');
            $table->bigInteger('spec_id')->comment('规格项 ID');
            $table->string('value', 64)->comment('规格值');
            $table->string('image_url', 255)->comment('规格值主图');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spec_value');
    }
};
