<?php

use App\Http\Controllers\Admin\v1\AttachmentController;
use App\Http\Controllers\Admin\v1\AuthController;
use App\Http\Controllers\Admin\V1\BrandController;
use App\Http\Controllers\Admin\v1\CategoryController;
use App\Http\Controllers\Admin\v1\FolderController;
use App\Http\Controllers\Admin\v1\PermissionController;
use App\Http\Controllers\Admin\V1\ProductSpecTemplateController;
use App\Http\Controllers\Admin\v1\RoleController;
use App\Http\Controllers\Admin\v1\UserColumnPreferenceController;
use App\Http\Controllers\Admin\v1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {

    Route::prefix('v1')->group(function () {

        Route::post('login', [AuthController::class, 'login']);

        // 专门用于刷新 Token 的路由（只允许 Refresh Token）
        Route::middleware([
            'auth:sanctum',
            'ability:refresh-token'
        ])->post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::middleware(['auth:sanctum', 'ability:access-api'])->group(function () {
            // 写在 auth:sanctum middleware 里，才能获取到用户信息
            Route::post('logout', [AuthController::class, 'logout']);


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
                Route::put('/{id}/status', [RoleController::class, 'updateStatus'])->name('role.updateStatus')->middleware('can:role.updateStatus');
            });

            // 用户管理
            Route::prefix('user')->group(function () {
                Route::get('/info', [AuthController::class, 'getUserInfo']);
                Route::get('/permissions', [AuthController::class, 'getPermissions'])->name('user.permissions')->middleware('can:user.permissions');
                Route::get('/', [UserController::class, 'index'])->name('user.index')->middleware('can:user.index');
                Route::post('/', [UserController::class, 'store'])->name('user.store')->middleware('can:user.store');
                Route::put('/{id}', [UserController::class, 'update'])->name('user.update')->middleware('can:user.update');
                Route::delete('/{id}', [UserController::class, 'destroy'])->name('user.destroy')->middleware('can:user.destroy');
                Route::get('/{id}', [UserController::class, 'show'])->name('user.show')->middleware('can:user.show');
                Route::put('/{id}/status', [UserController::class, 'updateStatus'])->name('user.updateStatus')->middleware('can:user.updateStatus');
            });

            // 文件夹管理
            Route::prefix('folder')->group(function () {
                Route::get('/tree', [FolderController::class, 'tree'])->name('folder.tree')->middleware('can:folder.tree');
                Route::post('/', [FolderController::class, 'store'])->name('folder.store')->middleware('can:folder.store');
                Route::delete('/{id}', [FolderController::class, 'destroy'])->name('folder.destroy')->middleware('can:folder.destroy');
            });

            // 附件管理
            Route::prefix('attachment')->group(function () {
                Route::get('/', [AttachmentController::class, 'index'])->name('attachment.index');
                Route::delete('/', [AttachmentController::class, 'destroy'])->name('attachment.destroy');
                Route::put('/restore', [AttachmentController::class, 'restore'])->name('attachment.restore');
            });

            // 分类管理
            Route::prefix('category')->group(function () {
                Route::get('/', [CategoryController::class, 'index'])->name('category.index')->middleware('can:category.index');
                Route::post('/', [CategoryController::class, 'store'])->name('category.store')->middleware('can:category.store');
                Route::put('/{id}', [CategoryController::class, 'update'])->name('category.update')->middleware('can:category.update');
                Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('category.destroy')->middleware('can:category.destroy');
            });

            // 品牌管理
            Route::prefix('brand')->group(function () {
                Route::get('/', [BrandController::class, 'index'])->name('brand.index')->middleware('can:brand.index');
                Route::post('/', [BrandController::class, 'store'])->name('brand.store')->middleware('can:brand.store');
                Route::put('/status', [BrandController::class, 'updateStatus'])->name('brand.updateStatus')->middleware('can:brand.updateStatus');
                Route::put('/{id}', [BrandController::class, 'update'])->name('brand.update')->middleware('can:brand.update');
                Route::delete('/{id}', [BrandController::class, 'destroy'])->name('brand.destroy')->middleware('can:brand.destroy');
            });

            // 规格模板管理
            Route::prefix('spec-template')->group(function () {
                Route::get('/', [ProductSpecTemplateController::class, 'index'])->name('spec-template.index')->middleware('can:spec-template.index');
                Route::post('/', [ProductSpecTemplateController::class, 'store'])->name('spec-template.store')->middleware('can:spec-template.store');
                Route::put('/status', [ProductSpecTemplateController::class, 'updateStatus'])->name('spec-template.updateStatus')->middleware('can:spec-template.updateStatus');
                Route::put('/{id}', [ProductSpecTemplateController::class, 'update'])->name('spec-template.update')->middleware('can:spec-template.update');
                Route::delete('/{id}', [ProductSpecTemplateController::class, 'destroy'])->name('spec-template.destroy')->middleware('can:spec-template.destroy');
            });
        });

        Route::prefix('common')->group(function () {
            Route::get('/user-column-preference/{key}', [UserColumnPreferenceController::class, 'show'])->name('user-column-preference.show');
            Route::post('/user-column-preference', [UserColumnPreferenceController::class, 'store'])->name('user-column-preference.store');

            Route::post('/upload', [AttachmentController::class, 'upload'])->name('attachment.upload');
        });
    });
});
