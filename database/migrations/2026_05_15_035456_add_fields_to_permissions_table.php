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
        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('label')->comment('权限名称');
            $table->string('path')->nullable()->comment('路由路径');
            $table->string('icon')->nullable()->comment('图标');
            $table->tinyInteger('type')->default(1)->comment('1菜单 2按钮');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('is_auth')->default(1)->comment('是否需要权限控制');
            $table->string('remark')->nullable()->comment('备注');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'parent_id',
                'label',
                'path',
                'icon',
                'type',
                'sort',
                'is_auth',
                'remark'
            ]);
        });
    }
};
