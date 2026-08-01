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
        Schema::table('attachment_folders', function (Blueprint $table) {
            $table->tinyInteger('recycle')->default(0)->comment('是否回收站 0=否 1=是')->after('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachment_folders', function (Blueprint $table) {
            $table->dropColumn('recycle');
        });
    }
};
