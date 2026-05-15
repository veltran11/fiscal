<?php
/**
 * Genera bundles de JS con hash para evitar caché del CDN.
 * 
 * Uso: <script src="bundles/app-<hash>.js"></script>
 * 
 * Cuando app.js cambia, el hash cambia y el nombre del archivo es diferente,
 * forzando al CDN y al navegador a descargar la nueva versión.
 */

// Determinar qué bundle generar
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
preg_match('#bundles/app-([a-f0-9]+)\.js#', $requestUri, $matches);

if (empty($matches)) {
    http_response_code(404);
    echo '/* Bundle not found */';
    exit;
}

$expectedHash = $matches[1];

// Combinar todos los JS en orden
$jsDir = __DIR__ . '/js';
$files = [
    'utils/EventBus.js',
    'services/ApiService.js',
    'services/AuthService.js',
    'components/Component.js',
    'components/Navbar.js',
    'router/Router.js',
    'views/BaseView.js',
    'views/LoginView.js',
    'views/RegisterView.js',
    'views/OlvideView.js',
    'views/DashboardView.js',
    'views/MiCuentaView.js',
    'views/ClientesView.js',
    'views/ClienteState.js',
    'views/ClienteFormView.js',
    'views/FacturasView.js',
    'views/FacturaState.js',
    'views/FacturaFormView.js',
    'views/CertificadosView.js',
    'app.js',
];

// Construir el bundle
$bundle = '';
foreach ($files as $file) {
    $path = $jsDir . '/' . $file;
    if (file_exists($path)) {
        // Remover imports/export para hacerlo inline
        $content = file_get_contents($path);
        $content = preg_replace('/^import\s+.*?;\s*$/m', '', $content);
        $content = preg_replace('/^export\s+/m', '', $content);
        $bundle .= "// === $file ===\n" . trim($content) . "\n\n";
    }
}

// Verificar hash
$actualHash = md5($bundle);

if ($expectedHash !== $actualHash) {
    http_response_code(404);
    echo "/* Hash mismatch. Expected $expectedHash, actual $actualHash */";
    exit;
}

// Cache largo pero el nombre único ya garantiza frescura
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
echo $bundle;
