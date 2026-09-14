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
        $payload = [
            'code' => 200,
            'msg' => $msg,
            'data' => $data,
        ];

        if (is_array($data) && array_key_exists('total', $data)) {
            $payload = array_merge($payload, $data);
        } else {
            $payload['data'] = $data;
        }

        return response()->json($payload);
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
}
