<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * 成功响应
     */
    public function success($data = [], $msg = 'ok')
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
    public function error(
        string $msg = 'error',
        int $code = 0,
        $data = []
    ) {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }
}
