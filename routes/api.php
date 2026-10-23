<?php

use App\Http\Controllers\Admin\v1\Product\CategoryController;
use App\Http\Controllers\Api\v1\Auth\AuthController;
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

        // 认证
        Route::prefix('auth')->group(function () {
            // 短信验证码接口限制频率，防止被刷
            Route::post('sendLoginCode', [AuthController::class, 'sendLoginCode'])->middleware('throttle:5,1');
            Route::post('login', [AuthController::class, 'login']);
        });

        // 专门用于刷新 Token 的路由（只允许 Refresh Token）
        Route::middleware([
            'auth:app',
            'ability:refresh-token'
        ])->post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::middleware(['auth:app', 'ability:access-api'])->group(function () {
            // 写在 auth:app middleware 里，才能获取到用户信息
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('user-info', [AuthController::class, 'getUserInfo']);
        });
    });
});
