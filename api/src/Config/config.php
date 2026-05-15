<?php

$config = [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'fact',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'jwt' => [
        'secret'  => 'CAMBIA_ESTE_SECRETO_EN_PRODUCCION',
        'expires' => 3600, // segundos
    ],
    'cors' => [
        'allowed_origin' => '*',
    ],
    'afip' => [
        'produccion' => true, // true = producción, false = homologación
    ],
    'mail' => [
        'from'      => 'noreply@tudominio.com',
        'from_name' => 'Sistema de Facturación',

        // SMTP — se deja vacío por seguridad; crear config.local.php con los datos reales
        'smtp' => [
            'host'     => '',
            'port'     => 587,
            'secure'   => 'tls',
            'username' => '',
            'password' => '',
        ],
    ],
];

$local = __DIR__ . '/config.local.php';
if (file_exists($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
