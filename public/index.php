<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FACTURación</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link rel="stylesheet" href="css/out.css" />
</head>

<body class="bg-gray-100 h-dvh">

  <div id="app" class="h-full flex flex-col">
    <!-- Navbar -->
    <div id="navbar-outlet"></div>

    <!-- Toast notifications -->
    <div id="toast-outlet" class="fixed top-4 right-4 z-50 flex flex-col gap-2"></div>

    <!-- Main view -->
    <main id="view-outlet" class="p-4 flex-1 overflow-hidden"></main>
  </div>

  <?php
  // Cache busting: agrega timestamp de modificación del JS como versión
  $jsFile = __DIR__ . '/js/app.js';
  $version = file_exists($jsFile) ? filemtime($jsFile) : time();
  ?>
  <script type="module" src="js/app.js?v=<?= $version ?>"></script>
</body>

</html>