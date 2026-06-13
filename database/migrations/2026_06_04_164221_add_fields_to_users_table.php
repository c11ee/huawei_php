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
        Schema::table('users', function (Blueprint $table) {
            // 添加字段像
            $table->string('avatar')->nullable()->after('password');
            // 添加字段用户名
            $table->string('username')->after('password');
            // 添加字段手机号
            $table->string('phone')->after('username');
            // 添加字段状态
            $table->integer('status')->after('phone')->default(0);
            // 添加字段角色ID
            $table->string('role_ids')->after('status')->default('');
            // 删除 email 字段
            $table->dropColumn('email');
            // 删除 email_verified_at 字段
            $table->dropColumn('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 删除字段像
            $table->dropColumn('avatar');
            // 删除字段用户名
            $table->dropColumn('username');
            // 删除字段手机号
            $table->dropColumn('phone');
            // 删除字段状态
            $table->dropColumn('status');
            // 删除字段角色ID
            $table->dropColumn('role_ids');
            // 添加字段 email
            $table->string('email')->after('name');
            // 添加字段 email_verified_at
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }
};
