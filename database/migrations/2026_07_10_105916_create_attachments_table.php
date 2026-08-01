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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('folder_id');
            $table->string('original_name')->comment('原始文件名');
            $table->string('file_path')->comment('本地存储相对路径');
            $table->string('file_url')->comment('完整 URL');
            $table->string('extension', 10)->comment('后缀');
            $table->unsignedBigInteger('file_size')->comment('文件大小 Byte');
            $table->string('mime_type')->comment("Mime 类型");
            $table->timestamps();

            // 索引优化
            $table->index(['user_id', 'folder_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
