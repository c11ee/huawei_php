<?php

use App\Http\Controllers\Api\v1\PermissionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('v1/permissions', PermissionController::class)->parameters([
    // 在路由里映射
    'permissions' => 'id',
])
    // 排除 show 方法
    ->except(['show']);
