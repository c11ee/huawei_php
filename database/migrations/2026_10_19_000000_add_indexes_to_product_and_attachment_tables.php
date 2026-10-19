<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 说明：部分索引此前只存在于数据库中、未记录在迁移文件里，
     * 这里统一补录，并全部使用「不存在才创建」的方式，避免重复建索引。
     */
    public function up(): void
    {
        // ==================== 用户表 ====================
        // 登录按 username 查询，手机号有唯一性校验
        $this->addUniqueIfNotExists('users', ['username'], 'uk_users_username');
        $this->addUniqueIfNotExists('users', ['phone'], 'uk_users_phone');
        // 库中已有，补录防止线上缺失
        $this->addIndexIfNotExists('users', ['email'], 'users_email_index');

        // ==================== 商品 SPU ====================
        // 商品编码唯一，生成编码时按 spu_code 查询
        $this->addUniqueIfNotExists('product_spu', ['spu_code'], 'uk_product_spu_spu_code');
        // 后台默认列表：deleted_at IS NULL order by sort
        $this->addIndexIfNotExists('product_spu', ['deleted_at', 'sort'], 'idx_spu_deleted_sort');
        // 后台按状态筛选 + 排序，同时覆盖各状态数量统计
        $this->addIndexIfNotExists('product_spu', ['deleted_at', 'status', 'sort'], 'idx_spu_deleted_status_sort');
        // 首页新品：status = 1 order by created_at desc
        $this->addIndexIfNotExists('product_spu', ['deleted_at', 'status', 'created_at'], 'idx_spu_deleted_status_created');
        // 库中已有，补录防止线上缺失
        $this->addIndexIfNotExists('product_spu', ['deleted_at'], 'product_spu_deleted_at_index');
        $this->addIndexIfNotExists('product_spu', ['status'], 'product_spu_status_index');

        // ==================== 商品 SKU ====================
        // SKU 编码唯一，生成编码时按 sku_code 查询
        $this->addUniqueIfNotExists('product_sku', ['sku_code'], 'uk_product_sku_sku_code');

        // ==================== 附件 ====================
        // 文件列表：folder_id + recycle 过滤
        $this->addIndexIfNotExists('attachments', ['folder_id', 'recycle'], 'attachments_folder_id_recycle_index');

        // 目录列表：parent_id + recycle 过滤并排序；回收站按 recycle 过滤
        $this->addIndexIfNotExists('attachment_folders', ['parent_id', 'recycle', 'sort'], 'idx_folder_parent_recycle_sort');
        $this->addIndexIfNotExists('attachment_folders', ['recycle'], 'idx_folder_recycle');
        // 单列 parent_id 索引已被上面的联合索引最左前缀覆盖
        $this->dropIndexIfExists('attachment_folders', 'attachment_folders_parent_id_index');

        // ==================== 商品分类 ====================
        // 首页楼层查询：status = 1 and is_home = 1 order by sort
        $this->addIndexIfNotExists('product_category', ['status', 'is_home', 'sort'], 'idx_category_status_home_sort');

        // ==================== 商品规格值 ====================
        // 编辑商品时按 spu_id 批量删除规格值
        $this->addIndexIfNotExists('product_spec_value', ['spu_id'], 'product_spec_value_spu_id_index');

        // ==================== 商品 SPU 分类关联 ====================
        // 按分类反查商品 + 防止重复绑定
        $this->addIndexIfNotExists('product_spu_category', ['category_id'], 'product_spu_category_category_id_index');
        $this->addUniqueIfNotExists('product_spu_category', ['spu_id', 'category_id'], 'uk_spu_category');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('product_spu_category', 'uk_spu_category');
        $this->dropIndexIfExists('product_spu_category', 'product_spu_category_category_id_index');
        $this->dropIndexIfExists('product_spec_value', 'product_spec_value_spu_id_index');
        $this->dropIndexIfExists('product_category', 'idx_category_status_home_sort');

        $this->dropIndexIfExists('attachment_folders', 'idx_folder_parent_recycle_sort');
        $this->dropIndexIfExists('attachment_folders', 'idx_folder_recycle');
        $this->addIndexIfNotExists('attachment_folders', ['parent_id'], 'attachment_folders_parent_id_index');

        $this->dropIndexIfExists('attachments', 'attachments_folder_id_recycle_index');

        $this->dropIndexIfExists('product_sku', 'uk_product_sku_sku_code');
        $this->addIndexIfNotExists('product_sku', ['sku_code'], 'product_sku_sku_code_index');

        $this->dropIndexIfExists('product_spu', 'uk_product_spu_spu_code');
        $this->dropIndexIfExists('product_spu', 'idx_spu_deleted_sort');
        $this->dropIndexIfExists('product_spu', 'idx_spu_deleted_status_sort');
        $this->dropIndexIfExists('product_spu', 'idx_spu_deleted_status_created');
        $this->dropIndexIfExists('product_spu', 'product_spu_deleted_at_index');
        $this->dropIndexIfExists('product_spu', 'product_spu_status_index');
        $this->addIndexIfNotExists('product_spu', ['spu_code'], 'product_spu_spu_code_index');

        $this->dropIndexIfExists('users', 'uk_users_username');
        $this->dropIndexIfExists('users', 'uk_users_phone');
        $this->dropIndexIfExists('users', 'users_email_index');
        $this->addIndexIfNotExists('users', ['username'], 'users_username_index');
    }

    /**
     * 普通索引不存在时创建
     */
    private function addIndexIfNotExists(string $table, array $columns, ?string $name = null): void
    {
        if ($this->hasIndexOnColumns($table, $columns)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    /**
     * 唯一索引不存在时创建；若已存在普通索引则替换为唯一索引
     */
    private function addUniqueIfNotExists(string $table, array $columns, string $name): void
    {
        foreach (collect(Schema::getIndexes($table)) as $index) {
            if ($index['columns'] !== $columns) {
                continue;
            }

            // 已经是唯一索引，无需处理
            if ($index['unique']) {
                return;
            }

            // 已存在普通索引，先删除再重建为唯一索引
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
            break;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    /**
     * 判断指定字段上是否已存在索引
     */
    private function hasIndexOnColumns(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['columns'] === $columns
        );
    }

    /**
     * 索引存在时才删除
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = collect(Schema::getIndexes($table))->contains(
            fn (array $item) => $item['name'] === $index
        );

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
        }
    }
};
