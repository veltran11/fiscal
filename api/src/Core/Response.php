<?php

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['success' => false, 'message' => $message], $status);
    }

    public static function unauthorized(string $message = 'No autorizado'): never
    {
        self::error($message, 401);
    }

    public static function notFound(string $message = 'No encontrado'): never
    {
        self::error($message, 404);
    }
}
