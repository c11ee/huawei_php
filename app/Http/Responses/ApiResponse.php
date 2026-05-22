<?php

namespace App\Http\Responses;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

class ApiResponse
{
    private const DATE_KEYS = ['created_at', 'updated_at', 'deleted_at'];

    private const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * 成功响应
     */
    public static function success($data = [], string $msg = 'ok')
    {
        return response()->json([
            'code' => 200,
            'msg' => $msg,
            'data' => static::formatData($data),
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
            'data' => static::formatData($data),
        ]);
    }

    /**
     * 统一格式化响应数据中的时间字段
     */
    private static function formatData(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            return static::formatData($data->toArray());
        }

        if ($data instanceof DateTimeInterface) {
            return $data->format(static::DATE_FORMAT);
        }

        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (in_array($key, static::DATE_KEYS, true)) {
                $data[$key] = static::formatDateValue($value);
                continue;
            }

            $data[$key] = static::formatData($value);
        }

        return $data;
    }

    private static function formatDateValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(static::DATE_FORMAT);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->format(static::DATE_FORMAT);
        }

        return $value;
    }
}
