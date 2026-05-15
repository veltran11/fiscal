<?php

namespace App\Models;

class User extends BaseModel
{
    protected string $table = 'usuarios';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function safe(array $user): array
    {
        unset($user['password'], $user['token_verificacion'], $user['token_reset']);
        return $user;
    }

    public function findByResetToken(string $token): ?array
    {
        return $this->findBy('token_reset', $token);
    }
}
