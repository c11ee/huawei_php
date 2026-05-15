<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // 只处理 API 请求
            if ($request->expectsJson()) {
                // ✅ 1. 表单验证错误
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'code' => 0,
                        // 获取第一个错误
                        'message' => $e->validator->errors()->first(),
                    ], 200);
                }

                // ✅ 2. HTTP异常（404 / 403 / 401）
                if ($e instanceof HttpExceptionInterface) {
                    return response()->json([
                        'code' => $e->getStatusCode(),
                        'message' => $e->validator->errors()->first(),
                    ], $e->getStatusCode());
                }

                // ✅ 3. 其他异常（500）
                return response()->json([
                    'code' => 500,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });
    })->create();
