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
        Schema::create('product_spec_template', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->default('')->comment('规格模板名称');
            $table->json('spec_json')->nullable()->comment('规格JSON');
            $table->tinyInteger('status')->default(1)->comment('状态');
            $table->integer('sort')->default(0)->comment('排序');
            $table->bigInteger('created_by')->comment('创建人');
            $table->bigInteger('updated_by')->comment('更新人');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spec_template');
    }
};
