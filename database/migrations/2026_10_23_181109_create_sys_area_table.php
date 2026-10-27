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
        Schema::create('sys_area', function (Blueprint $table) {
            $table->comment('系统行政区划表');

            $table->id();
            $table->bigInteger('parent_id')->default(0)->comment('父级ID');
            $table->tinyInteger('level')->default(0)->comment('层级: 0省 1市 2区 3街道');
            $table->string('name', 50)->comment('短名称 (如: 北京)');
            $table->string('ext_name', 100)->comment('标准全称 (如: 北京市)');
            $table->string('ext_id', 20)->comment('国家标准代码/高德代码 (如: 110000000000)');
            $table->string('pinyin', 100)->comment('全拼音');
            $table->string('pinyin_prefix', 10)->comment('拼音首字母');
            $table->tinyInteger('status')->default(1)->comment('状态');
            $table->timestamps();

            $table->index('parent_id', 'idx_parent_id');
            $table->index('ext_id', 'idx_ext_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_area');
    }
};
