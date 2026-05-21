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
}
