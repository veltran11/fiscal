<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\JwtMiddleware;
use App\Models\User;

class AuthController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function login(Request $request): void
    {
        $email    = $request->body('email');
        $password = $request->body('password');

        if (!$email || !$password) {
            Response::error('Email y contraseña son requeridos');
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !$this->userModel->verifyPassword($password, $user['password'])) {
            Response::unauthorized('Credenciales incorrectas');
        }

        if (!$user['activo']) {
            Response::unauthorized('Usuario inactivo');
        }

        $token = JwtMiddleware::generate([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'rol'   => $user['rol'],
        ]);

        Response::success([
            'token' => $token,
            'user'  => $this->userModel->safe($user),
        ], 'Login exitoso');
    }

    public function me(Request $request): void
    {
        $payload = $this->requireAuth($request);
        $user    = $this->userModel->find($payload['sub']);

        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success($this->userModel->safe($user));
    }
}
