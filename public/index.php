<?php
// Forzar no caché del HTML principal
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$jsFile = __DIR__ . '/js/app.js';
$version = file_exists($jsFile) ? filemtime($jsFile) : time();
$cssFile = __DIR__ . '/css/out.css';
$cssVersion = file_exists($cssFile) ? filemtime($cssFile) : time();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FACTURación - VERSION 2025</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link rel="stylesheet" href="css/out.css?v=<?= $cssVersion ?>" />
</head>

<body class="bg-gray-100 h-dvh">

  <!-- DEBUG: v=<?= $version ?> -->

  <div id="app" class="h-full flex flex-col">
    <!-- Navbar -->
    <div id="navbar-outlet"></div>

    <!-- Toast notifications -->
    <div id="toast-outlet" class="fixed top-4 right-4 z-50 flex flex-col gap-2"></div>

    <!-- Main view -->
    <main id="view-outlet" class="p-4 flex-1 overflow-hidden"></main>
  </div>

  <?php
  // El hash se calcula igual que en bundle.php para que coincidan
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
  $bundle = '';
  foreach ($files as $file) {
    $path = $jsDir . '/' . $file;
    if (file_exists($path)) {
      $content = file_get_contents($path);
      $content = preg_replace("/^import\s+.*?from\s+['\"].*?['\"];?\s*$/m", '', $content);
      $content = preg_replace('/^export\s+/m', '', $content);
      $content = str_replace("import.meta.url", "window.location.origin + '/api'", $content);
      $bundle .= trim($content) . "\n\n";
    }
  }
  $hash = md5($bundle);
  ?>
  <script type="module" src="bundle.php?hash=<?= $hash ?>"></script>
</body>

</html>