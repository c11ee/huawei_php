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
        Schema::create('app_address', function (Blueprint $table) {
            $table->comment('用户收货地址表');

            $table->id();
            $table->foreignId('user_id')->comment('用户ID')->constrained('app_user')->cascadeOnDelete();
            $table->unsignedTinyInteger('is_default')->default(0)->comment('是否默认地址 0: 否 1: 是');
            $table->string('mobile', 20)->comment('手机号');
            $table->string('name', 64)->comment('收货人姓名');
            // 关联 sys_area.id，不建外键：区划数据会整批重建，避免约束阻塞导入
            $table->unsignedBigInteger('province_id')->comment('省份ID');
            $table->unsignedBigInteger('city_id')->comment('城市ID');
            $table->unsignedBigInteger('district_id')->comment('区县ID');
            $table->unsignedBigInteger('township_id')->default(0)->comment('乡镇/街道ID 0: 未匹配');
            $table->string('address', 255)->comment('详细地址');
            $table->timestamps();

            // 地址列表按用户查询并按默认地址排序
            $table->index(['user_id', 'is_default'], 'idx_user_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_address');
    }
};
