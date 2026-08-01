<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/** 下载文件 */
Route::get('/file/download/{path}', function (string $path) {
    // 防止目录穿越
    $path = str_replace(['../', '..\\'], '', $path);

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    $filePath = Storage::disk('public')->path($path);   // 获取真实物理路径
    $fileName = basename($path);

    return response()->download($filePath, $fileName, [
        'Access-Control-Allow-Origin'      => '*',                    // 开发阶段用 *
        // 'Access-Control-Allow-Origin'   => 'http://127.0.0.1:8848', // 正式环境建议写死前端域名
        'Access-Control-Allow-Methods'     => 'GET, OPTIONS',
        'Access-Control-Allow-Headers'     => '*',
        'Access-Control-Expose-Headers'    => 'Content-Disposition',
    ]);
})->where('path', '.*');
