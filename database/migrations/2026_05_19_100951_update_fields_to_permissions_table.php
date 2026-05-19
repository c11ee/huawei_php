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
            $table->string('path')->default('')->nullable(false)->change();
            $table->string('icon')->default('')->nullable(false)->change();
            $table->string('remark')->default('')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('path');
            $table->dropColumn('icon');
            $table->dropColumn('remark');
        });
    }
};
