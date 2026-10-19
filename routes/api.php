<?php

use App\Http\Controllers\Admin\v1\Product\CategoryController;
use App\Http\Controllers\Api\v1\Home\HomeController;
use App\Http\Controllers\Api\v1\Product\ProductController;
use App\Http\Controllers\Api\v1\Search\SearchController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/admin.php';

Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        // 分类管理
        Route::prefix('category')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
        });

        // 首页
        Route::get('home', [HomeController::class, 'index']);

        // 搜索
        Route::get('search', [SearchController::class, 'index']);

        // 测试
        Route::get('test', [TestController::class, 'index']);

        // 商品管理
        Route::prefix('product')->group(function () {
            Route::get('/{spu_id}', [ProductController::class, 'show']);
        });
    });
});
