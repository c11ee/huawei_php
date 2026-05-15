<?php

use App\Http\Controllers\Api\PermissionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('permissions', PermissionController::class);
