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
        Schema::create('category', function (Blueprint $table) {
            $table->id();
            $table->string('category_name', 100)->default('')->comment('分类名称');
            $table->bigInteger('parent_id')->default(0)->comment('父分类ID');
            $table->integer('sort')->default(0)->comment('排序字段');
            $table->timestamps();

            $table->index(['parent_id', 'sort'], 'idx_parent_order');

            $table->comment('前端分类导航配置表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category');
    }
};
