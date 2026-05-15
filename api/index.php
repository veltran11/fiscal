<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\CertificadosController;
use App\Controllers\ClientesController;
use App\Controllers\CuentaController;
use App\Controllers\FacturasController;

// CORS
$cfg = require __DIR__ . '/src/Config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: '  . $cfg['cors']['allowed_origin']);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = new Request();
$router  = new Router();

// --- Rutas ---
$router->post('/api/auth/login',     [AuthController::class, 'login']);
$router->get('/api/auth/me',         [AuthController::class, 'me']);
$router->post('/api/auth/register',  [AuthController::class, 'register']);
$router->get('/api/auth/verificar',  [AuthController::class, 'verificar']);
$router->post('/api/auth/olvide',    [AuthController::class, 'olvidePassword']);
$router->get('/api/auth/restablecer',  [AuthController::class, 'restablecerFormulario']);
$router->post('/api/auth/restablecer', [AuthController::class, 'restablecer']);

$router->get('/api/cuenta',         [CuentaController::class, 'get']);
$router->put('/api/cuenta',         [CuentaController::class, 'save']);
$router->post('/api/cuenta/padron', [CuentaController::class, 'padron']);

$router->get('/api/clientes',          [ClientesController::class, 'index']);
$router->post('/api/clientes',         [ClientesController::class, 'store']);
$router->put('/api/clientes/{id}',     [ClientesController::class, 'update']);
$router->delete('/api/clientes/{id}',  [ClientesController::class, 'destroy']);

$router->get('/api/certificados',        [CertificadosController::class, 'estado']);
$router->post('/api/certificados/generar', [CertificadosController::class, 'generar']);
$router->get('/api/certificados/csr',    [CertificadosController::class, 'csr']);
$router->post('/api/certificados/subir', [CertificadosController::class, 'subir']);

$router->get('/api/facturas',          [FacturasController::class, 'index']);
$router->post('/api/facturas',         [FacturasController::class, 'store']);
$router->put('/api/facturas/{id}',     [FacturasController::class, 'update']);
$router->delete('/api/facturas/{id}',  [FacturasController::class, 'destroy']);

// --- Dispatcher ---
try {
    $router->dispatch($request);
} catch (Throwable $e) {
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
