<?php

/**
 * Genera un bundle único de JS con hash.
 * Se sirve desde public/bundle.php?hash=XXXXX
 * 
 * El hash cambia cuando el código cambia, forzando al CDN
 * a descargar la nueva versión.
 */

$expectedHash = $_GET['hash'] ?? '';

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

// Construir el bundle sin imports/exports
$bundle = '';
foreach ($files as $file) {
    $path = $jsDir . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        // Remover import ... from '...'
        $content = preg_replace("/^import\s+.*?from\s+['\"].*?['\"];?\s*$/m", '', $content);
        // Remover export 
        $content = preg_replace('/^export\s+/m', '', $content);
        // Remover import.meta
        $content = str_replace("import.meta.url", "window.location.origin + '/api'", $content);
        $bundle .= "// === $file ===\n" . trim($content) . "\n\n";
    }
}

// Verificar hash
$actualHash = md5($bundle);

if ($expectedHash !== $actualHash) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Hash mismatch. Expected: $expectedHash, Actual: $actualHash";
    exit;
}

header('Content-Type: application/javascript; charset=utf-8');
// Cache largo - el nombre único ya garantiza que sea fresco
header('Cache-Control: public, max-age=31536000, immutable');
echo $bundle;
