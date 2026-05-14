<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class JwtMiddleware
{
    public static function handle(Request $request): array
    {
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Token no proporcionado');
        }

        $payload = self::decode($token);

        if (!$payload) {
            Response::unauthorized('Token inválido o expirado');
        }

        return $payload;
    }

    public static function generate(array $payload): string
    {
        $cfg     = require __DIR__ . '/../Config/config.php';
        $secret  = $cfg['jwt']['secret'];
        $expires = $cfg['jwt']['expires'];

        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims  = self::base64url(json_encode(array_merge($payload, [
            'iat' => time(),
            'exp' => time() + $expires,
        ])));

        $signature = self::base64url(hash_hmac('sha256', "$header.$claims", $secret, true));

        return "$header.$claims.$signature";
    }

    private static function decode(string $token): ?array
    {
        $cfg    = require __DIR__ . '/../Config/config.php';
        $secret = $cfg['jwt']['secret'];

        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $claims, $signature] = $parts;

        $expected = self::base64url(hash_hmac('sha256', "$header.$claims", $secret, true));
        if (!hash_equals($expected, $signature)) return null;

        $payload = json_decode(self::base64urlDecode($claims), true);
        if (!$payload || $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
