<?php

use App\Http\Controllers\Admin\v1\AuthController;
use App\Http\Controllers\Admin\v1\PermissionController;
use App\Http\Controllers\Admin\v1\RoleController;
use App\Http\Controllers\Admin\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {

    Route::prefix('v1')->group(function () {

        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            // 写在 auth:sanctum middleware 里，才能获取到用户信息
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('userinfo', [AuthController::class, 'getUserInfo']);

            // 权限管理
            Route::prefix('permissions')->group(function () {
                Route::get('/', [PermissionController::class, 'index'])->name('permissions.index')->middleware('can:permissions.index');
                Route::post('/', [PermissionController::class, 'store'])->name('permissions.store')->middleware('can:permissions.store');
                Route::put('/{id}', [PermissionController::class, 'update'])->name('permissions.update')->middleware('can:permissions.update');
                Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('can:permissions.destroy');
            });

            // 角色管理
            Route::prefix('role')->group(function () {
                Route::get('/', [RoleController::class, 'index'])->name('role.index')->middleware('can:role.index');
                Route::post('/', [RoleController::class, 'store'])->name('role.store')->middleware('can:role.store');
                Route::put('/{id}', [RoleController::class, 'update'])->name('role.update')->middleware('can:role.update');
                Route::delete('/{id}', [RoleController::class, 'destroy'])->name('role.destroy')->middleware('can:role.destroy');
            });

            // 用户管理
            Route::prefix('user')->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('user.index')->middleware('can:user.index');
                Route::post('/', [UserController::class, 'store'])->name('user.store')->middleware('can:user.store');
                Route::put('/{id}', [UserController::class, 'update'])->name('user.update')->middleware('can:user.update');
                Route::delete('/{id}', [UserController::class, 'destroy'])->name('user.destroy')->middleware('can:user.destroy');
                Route::get('/{id}', [UserController::class, 'show'])->name('user.show')->middleware('can:user.show');
            });
        });
    });
});
