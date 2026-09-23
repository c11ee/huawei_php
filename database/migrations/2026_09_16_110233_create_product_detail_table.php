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
        Schema::create('product_detail', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16)->comment('详情标识 spu, sku');
            $table->bigInteger('owner_id')->comment('关联ID');
            $table->longText('detail_html')->comment('PC端详情HTML');
            $table->longText('mobile_detail_html')->comment('移动端详情HTML');

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id'], 'uk_owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_detail');
    }
};
