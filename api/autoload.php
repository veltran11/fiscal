<?php

spl_autoload_register(function (string $class): void {
    // Namespace raíz: App\ → api/src/
    $prefix = 'App\\';
    $base   = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
