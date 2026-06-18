<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 在这里注册 Sanctum 的 ability 中间件
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability'   => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // 只处理 API 请求
            if ($request->expectsJson()) {
                // ✅ 1. 表单验证错误
                if ($e instanceof ValidationException) {
                    return ApiResponse::error($e->validator->errors()->first());
                }

                // ✅ 2. 拦截 Token 无效 / 登录过期
                if ($e instanceof AuthenticationException) {
                    return ApiResponse::error('登录已过期或凭证无效，请重新登录', 401);
                }

                if ($e instanceof AccessDeniedHttpException) {
                    $method = $request->method(); // 获取请求方式：GET, POST, DELETE 等
                    $path = $request->path();     // 获取 API 路径：api/v1/role 等

                    $message = "操作失败：您没有权限请求该接口 [{$method} /{$path}]";

                    return ApiResponse::error($message, 403);
                }

                // ✅ 3. HTTP异常（404 / 403 / 401）
                if ($e instanceof HttpExceptionInterface) {
                    return ApiResponse::error($e->getMessage(), $e->getStatusCode());
                }

                // ✅ 4. 其他程序内部未知异常（500）
                return ApiResponse::error($e->getMessage(), 500);
            }
        });
    })->create();
