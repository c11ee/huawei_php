<?php

namespace App\Http\Responses;

use DateTimeInterface;

class ApiResponse
{
    /**
     * 成功响应
     */
    public static function success($data = [], string $msg = 'ok')
    {
        return response()->json([
            'code' => 200,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 失败响应
     */
    public static function error(string $msg = 'error', int $code = 0, $data = [])
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 格式化时间字段
     */
}
