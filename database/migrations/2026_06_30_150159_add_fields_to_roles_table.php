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
        Schema::table('roles', function (Blueprint $table) {
            // 状态字段
            $table->integer('status')->after('name')->default(0);
            // 角色描述
            $table->string('description')->after('status')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // 删除状态字段
            $table->dropColumn('status');
            // 删除角色描述
            $table->dropColumn('description');
        });
    }
};
