<?php

use App\Http\Controllers\Admin\v1\PermissionController;
use App\Http\Controllers\Admin\v1\RoleController;
use App\Http\Controllers\Admin\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
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
