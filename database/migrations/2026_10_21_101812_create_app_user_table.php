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
        Schema::create('app_user', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->default('')->comment('昵称');
            $table->string('avatar')->default('')->comment('头像');
            $table->string('mobile', 20)->nullable()->comment('手机号');
            $table->tinyInteger('status')->default(1)->comment('状态 0: 禁用 1: 正常');
            $table->timestamps();

            $table->unique('mobile', 'uk_user_mobile');
        });
        Schema::create('app_user_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->comment('用户ID')->constrained('app_user')->cascadeOnDelete();
            $table->unsignedTinyInteger('identity_type')->comment('认证类型 1: 账号密码 2: 手机号 3: 支付宝');
            $table->string('identifier', 128)->comment('登录标识: 账号/手机号/openid/unionid');
            $table->string('credential', 256)->nullable()->comment('登录凭证 密码hash/空(第三方)');
            $table->json('extra')->nullable()->comment('扩展信息');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();

            // 登录时按 (identity_type, identifier) 反查用户，需全局唯一
            $table->unique(['identity_type', 'identifier'], 'uk_auth_type_identifier');
            $table->index('user_id', 'idx_auth_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 先删子表，避免外键约束导致删除失败
        Schema::dropIfExists('app_user_auth');
        Schema::dropIfExists('app_user');
    }
};
