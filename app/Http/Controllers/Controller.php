<?php

namespace App\Http\Controllers;

use DateTimeInterface;

abstract class Controller
{
    /**
     * 序列化日期
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 解析逗号分隔的 ID 字符串, 过滤非正整数并去重
     */
    protected function parseIds(string|int|null $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $ids)),
            fn($v) => $v > 0
        )));
    }
}
