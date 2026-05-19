<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public static function success($data = [], $msg = 'ok')
    {
        return response()->json([
            'code' => 200,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    public static function error($msg = 'error', $code = 0, $data = [])
    {
        return response()->json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ]);
    }
}
