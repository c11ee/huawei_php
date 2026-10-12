<?php

use App\Http\Controllers\Admin\v1\Product\CategoryController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/admin.php';

Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        // 分类管理
        Route::prefix('category')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
        });

        Route::get('test', [TestController::class, 'index']);
    });
});
