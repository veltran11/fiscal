<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\JwtMiddleware;
use App\Models\User;
use App\Services\MailService;

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

    public function register(Request $request): void
    {
        $nombre   = trim($request->body('nombre') ?? '');
        $email    = trim($request->body('email') ?? '');
        $password = $request->body('password') ?? '';

        if (!$nombre || !$email || !$password) {
            Response::error('Todos los campos son obligatorios.');
        }
        if (strlen($password) < 6) {
            Response::error('La contraseña debe tener al menos 6 caracteres.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Email inválido.');
        }

        $existing = $this->userModel->findByEmail($email);

        if ($existing && $existing['activo']) {
            Response::error('Ya existe una cuenta activa con ese email.');
        }

        $token = bin2hex(random_bytes(32));
        $hash  = password_hash($password, PASSWORD_BCRYPT);

        if ($existing && !$existing['activo']) {
            // Actualizar datos y reenviar token
            $this->userModel->update($existing['id'], [
                'nombre'             => $nombre,
                'password'           => $hash,
                'token_verificacion' => $token,
            ]);
            $id = $existing['id'];
            $msg = 'Registro actualizado. Revisá tu email para activar la cuenta.';
        } else {
            $id = $this->userModel->create([
                'nombre'             => $nombre,
                'email'              => $email,
                'password'           => $hash,
                'rol'                => 'usuario',
                'activo'             => 0,
                'token_verificacion' => $token,
            ]);
            $msg = 'Registrado. Revisá tu email para activar la cuenta.';
        }

        // Enviar mail
        $cfg = require __DIR__ . '/../Config/config.php';
        $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = $baseUrl . '/api/auth/verificar?token=' . $token;

        $body = '<h2>Activá tu cuenta</h2>'
            . '<p>Hacé clic en el siguiente link para activar tu cuenta:</p>'
            . '<p><a href="' . $link . '">' . $link . '</a></p>';

        MailService::send($email, 'Activá tu cuenta', $body);

        Response::success(['id' => $id], $msg, 201);
    }

    public function olvidePassword(Request $request): void
    {
        $email = trim($request->body('email') ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Email inválido.');
        }

        $user = $this->userModel->findByEmail($email);

        // Siempre responder igual aunque el email no exista (seguridad)
        if (!$user || !$user['activo']) {
            Response::success([], 'Si el email existe y está activo, recibirás un link para restablecer tu contraseña.');
        }

        $token = bin2hex(random_bytes(32));

        $this->userModel->update($user['id'], [
            'token_reset' => $token,
        ]);

        // Enviar mail
        $cfg = require __DIR__ . '/../Config/config.php';
        $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $link = $baseUrl . '/api/auth/restablecer?token=' . $token;

        $body = '<h2>Restablecé tu contraseña</h2>'
            . '<p>Hacé clic en el siguiente link para crear una nueva contraseña:</p>'
            . '<p><a href="' . $link . '">' . $link . '</a></p>'
            . '<hr>'
            . '<p style="color:gray;font-size:0.85em">Si no solicitaste este cambio, ignorá este mensaje.</p>';

        MailService::send($email, 'Restablecé tu contraseña', $body);

        Response::success([], 'Si el email existe y está activo, recibirás un link para restablecer tu contraseña.');
    }

    public function restablecerFormulario(Request $request): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $token = trim($request->query('token') ?? '');

        if (!$token) {
            echo $this->htmlError('Token inválido', 'El link no contiene un token válido.');
            exit;
        }

        $user = $this->userModel->findByResetToken($token);

        if (!$user) {
            echo $this->htmlError('Token inválido o expirado', 'El link ya fue utilizado o el token no es válido.');
            exit;
        }

        // Mostrar formulario para nueva contraseña
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>Restablecer contraseña</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f3f4f6}'
            . '.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 4px 6px rgba(0,0,0,.1);max-width:420px;width:90%}'
            . 'h2{color:#2563eb;margin-bottom:8px}'
            . 'p{color:#374151;margin-bottom:24px;font-size:0.9em}'
            . 'input{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:1em;margin-bottom:16px;box-sizing:border-box}'
            . 'input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2)}'
            . 'button{width:100%;background:#2563eb;color:#fff;border:none;padding:12px;border-radius:8px;font-size:1em;font-weight:600;cursor:pointer}'
            . 'button:hover{background:#1d4ed8}'
            . '.error{color:#dc2626;font-size:0.9em;margin-top:-8px;margin-bottom:16px;display:none}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<h2>🔑 Restablecé tu contraseña</h2>'
            . '<p>Ingresá tu nueva contraseña</p>'
            . '<form id="form-reset" method="POST" action="/api/auth/restablecer">'
            . '<input type="hidden" name="token" value="' . htmlspecialchars($token) . '">'
            . '<input type="password" name="password" placeholder="Nueva contraseña (mín. 6 caracteres)" minlength="6" required>'
            . '<input type="password" id="confirmar" placeholder="Repetí la contraseña" minlength="6" required>'
            . '<div class="error" id="error-msg">Las contraseñas no coinciden</div>'
            . '<button type="submit">Cambiar contraseña</button>'
            . '</form>'
            . '<script>'
            . 'document.getElementById("form-reset").addEventListener("submit",function(e){'
            . 'var p=this.querySelector("[name=password]").value;'
            . 'var c=document.getElementById("confirmar").value;'
            . 'if(p!==c){e.preventDefault();document.getElementById("error-msg").style.display="block"}'
            . '})'
            . '</script>'
            . '</div></body></html>';
        exit;
    }

    public function restablecer(Request $request): void
    {
        header('Content-Type: text/html; charset=utf-8');

        // Soporta tanto JSON como form-urlencoded (POST de formulario HTML)
        $token    = trim($request->body('token') ?? $_POST['token'] ?? '');
        $password = $request->body('password') ?? $_POST['password'] ?? '';

        if (!$token || !$password) {
            echo $this->htmlError('Datos incompletos', 'Faltan datos para restablecer la contraseña.');
            exit;
        }

        if (strlen($password) < 6) {
            echo $this->htmlError('Contraseña muy corta', 'La contraseña debe tener al menos 6 caracteres.');
            exit;
        }

        $user = $this->userModel->findByResetToken($token);

        if (!$user) {
            echo $this->htmlError('Token inválido o expirado', 'El link ya fue utilizado o el token no es válido.');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->userModel->update($user['id'], [
            'password'   => $hash,
            'token_reset' => null,
        ]);

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>Contraseña restablecida</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f3f4f6}'
            . '.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 4px 6px rgba(0,0,0,.1);max-width:400px}'
            . 'h2{color:#2563eb;margin-bottom:16px}'
            . 'p{color:#374151;margin-bottom:20px}'
            . 'a{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:600}'
            . 'a:hover{background:#1d4ed8}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<h2>✅ Contraseña restablecida</h2>'
            . '<p>Tu contraseña se actualizó correctamente. Ya podés iniciar sesión.</p>'
            . '<a href="' . ($_SERVER['HTTP_ORIGIN'] ?? '/') . '">Ir al login</a>'
            . '</div></body></html>';
        exit;
    }

    public function verificar(Request $request): void
    {
        // Esta ruta responde con HTML, no JSON
        header('Content-Type: text/html; charset=utf-8');

        $token = trim($request->query('token') ?? '');

        if (!$token) {
            echo $this->htmlError('Token inválido', 'El link de verificación no contiene un token válido.');
            exit;
        }

        $db = \App\Core\Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE token_verificacion = ? AND activo = 0 LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            echo $this->htmlError('Token inválido o cuenta ya activada', 'El link ya fue utilizado o el token no es válido.');
            exit;
        }

        $stmt = $db->prepare('UPDATE usuarios SET activo = 1, token_verificacion = NULL WHERE id = ?');
        $stmt->execute([$user['id']]);

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>Cuenta activada</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f3f4f6}'
            . '.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 4px 6px rgba(0,0,0,.1);max-width:400px}'
            . 'h2{color:#2563eb;margin-bottom:16px}'
            . 'p{color:#374151;margin-bottom:20px}'
            . 'a{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:600}'
            . 'a:hover{background:#1d4ed8}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<h2>✅ Cuenta activada correctamente</h2>'
            . '<p>Ya podés iniciar sesión con tu usuario y contraseña.</p>'
            . '<a href="' . ($_SERVER['HTTP_ORIGIN'] ?? '/') . '">Ir al login</a>'
            . '</div></body></html>';
        exit;
    }

    private function htmlError(string $titulo, string $mensaje): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>' . $titulo . '</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f3f4f6}'
            . '.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 4px 6px rgba(0,0,0,.1);max-width:400px}'
            . 'h2{color:#dc2626;margin-bottom:16px}'
            . 'p{color:#374151;margin-bottom:0}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<h2>⚠️ ' . $titulo . '</h2>'
            . '<p>' . $mensaje . '</p>'
            . '</div></body></html>';
    }
}
