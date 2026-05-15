<?php

namespace App\Core;

class Config
{
    private static ?array $data = null;

    public static function get(): array
    {
        if (self::$data === null) {
            self::$data = require __DIR__ . '/../Config/config.php';
        }
        return self::$data;
    }
}
