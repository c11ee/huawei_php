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
        // 入驻申请表
        Schema::create('shop_apply', function (Blueprint $table) {
            $table->comment('入驻申请表');

            $table->id();
            $table->string('apply_no', 64)->unique()->comment('申请单号,唯一');
            $table->foreignId('user_id')->nullable()->constrained('app_user')->nullOnDelete()->comment('申请人用户ID');
            $table->unsignedTinyInteger('subject_type')->comment('主体类型 0:自然人 1:个体户 2:企业');
            $table->string('real_name', 255)->nullable()->comment('真实姓名(加密存储)');
            $table->string('id_card_no', 255)->nullable()->comment('身份证号(加密存储)');
            $table->char('id_card_no_hash', 64)->nullable()->comment('身份证号哈希,用于去重检索');
            $table->string('id_card_front', 255)->nullable()->comment('身份证正面(私有路径)');
            $table->string('id_card_back', 255)->nullable()->comment('身份证反面(私有路径)');
            $table->string('credit_code', 32)->nullable()->comment('统一社会信用代码');
            $table->string('business_license', 255)->nullable()->comment('营业执照(私有路径)');
            $table->string('subject_code', 64)->comment('主体唯一编号:自然人=身份证号哈希;个体户/企业=统一社会信用代码');
            $table->unsignedTinyInteger('is_small_amount')->default(0)->comment('是否零星小额: 1=年交易额≤10万,0=超过10万(自然人不得提交0)');
            $table->string('shop_name', 128)->comment('店铺名');
            $table->unsignedBigInteger('province_id')->comment('省份ID');
            $table->unsignedBigInteger('city_id')->comment('城市ID');
            $table->unsignedBigInteger('district_id')->comment('区县ID');
            $table->unsignedBigInteger('township_id')->default(0)->comment('乡镇/街道ID 0: 未匹配');
            $table->string('address', 255)->comment('详细地址');
            $table->unsignedTinyInteger('status')->default(0)->comment('审核状态 0:待审核 1:审核通过 2:审核驳回 3:用户撤销');
            $table->string('audit_remark', 255)->default('')->comment('审核备注');
            $table->string('contact_phone', 20)->comment('联系电话');
            $table->foreignId('auditor_id')->nullable()->constrained('users')->restrictOnDelete()->comment('审核管理员ID');
            $table->timestamp('audited_at')->nullable()->comment('审核时间');
            $table->timestamps();

            // user_id 已由外键自动建索引，这里用联合索引覆盖“用户查自己的申请”
            $table->index(['user_id', 'status'], 'idx_apply_user_status');
            $table->index(['status', 'created_at'], 'idx_apply_status_created');
            // 唯一性由 shop_admin 的主体唯一键保证，申请单允许用户驳回/撤销后再次提交
            $table->index('subject_code', 'idx_apply_subject_code');
            $table->index('id_card_no_hash', 'idx_apply_id_card_hash');
        });
        // 店铺账号表（商家登录用）
        Schema::create('shop_admin', function (Blueprint $table) {
            $table->comment('店铺账号表');

            $table->id();
            $table->foreignId('apply_id')->unique()->constrained('shop_apply')->restrictOnDelete()->comment('店铺申请ID');
            $table->unsignedTinyInteger('subject_type')->comment('主体类型 0:自然人 1:个体户 2:企业');
            $table->string('subject_code', 64)->comment('主体唯一编号,同申请单');
            $table->string('username', 64)->comment('用户名');
            $table->string('password', 255)->comment('密码');
            $table->string('contact_phone', 20)->comment('商家联系电话 用于重置密码');
            $table->unsignedTinyInteger('status')->default(1)->comment('账号状态 0:禁用 1:正常');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_code'], 'uk_shop_admin_subject_code')->comment('一个主体只能有一个店铺账号');
            $table->unique('username', 'uk_shop_admin_username')->comment('商家登录账号全平台唯一');
        });
        // 店铺主表 (审核通过才新增)
        Schema::create('shop', function (Blueprint $table) {
            $table->comment('店铺主表');

            $table->id();
            $table->foreignId('shop_admin_id')->constrained('shop_admin')->cascadeOnDelete()->comment('店铺账号ID');
            $table->unsignedTinyInteger('subject_type')->comment('主体类型 0:自然人 1:个体户 2:企业');
            $table->string('shop_name', 128)->comment('店铺名');
            $table->string('shop_intro', 255)->default('')->comment('店铺简介');
            $table->string('shop_logo', 255)->nullable()->comment('店铺logo');
            $table->unsignedBigInteger('province_id')->comment('省份ID');
            $table->unsignedBigInteger('city_id')->comment('城市ID');
            $table->unsignedBigInteger('district_id')->comment('区县ID');
            $table->unsignedBigInteger('township_id')->default(0)->comment('乡镇/街道ID 0: 未匹配');
            $table->string('address', 255)->default('')->comment('详细地址');
            $table->string('contact_phone', 20)->default('')->comment('联系电话');
            $table->unsignedBigInteger('follow_count')->default(0)->comment('关注人数');
            $table->decimal('product_score', 3, 2)->nullable()->comment('商品评分 0-5分, NULL=暂无评分');
            $table->decimal('logistics_score', 3, 2)->nullable()->comment('物流评分 0-5分, NULL=暂无评分');
            $table->decimal('after_sale_score', 3, 2)->nullable()->comment('售后评分 0-5分, NULL=暂无评分');
            $table->unsignedTinyInteger('status')->default(1)->comment('店铺状态 0:禁用 1:正常 2:封禁');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_shop_status');
            $table->index(['province_id', 'city_id'], 'idx_shop_region');
            $table->unique('shop_admin_id', 'uk_shop_shop_admin_id')->comment('一个店铺账号只能有唯一一个店铺');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop');
        Schema::dropIfExists('shop_admin');
        Schema::dropIfExists('shop_apply');
    }
};
