<?php

use App\Http\Controllers\Admin\v1\AuthController;
use App\Http\Controllers\Admin\v1\PermissionController;
use App\Http\Controllers\Admin\v1\RoleController;
use App\Http\Controllers\Admin\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::post('v1/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        // 写在 auth:sanctum middleware 里，才能获取到用户信息
        Route::post('v1/logout', [AuthController::class, 'logout']);
        Route::get('v1/userinfo', [AuthController::class, 'getUserInfo']);

        Route::apiResource('v1/permissions', PermissionController::class)->parameters([
            // 在路由里映射
            'permissions' => 'id',
        ])
            // 排除 show 方法
            ->except(['show']);

        Route::apiResource('v1/role', RoleController::class)->parameters([
            'role' => 'id',
        ])->except(['show']);

        Route::apiResource('v1/user', UserController::class)->parameters([
            'user' => 'id',
        ]);
    });
});
