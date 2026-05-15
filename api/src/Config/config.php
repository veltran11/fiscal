<?php

return [
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
        'from'      => 'hola@v11.com.ar',
        'from_name' => 'Fiscal',

        // SMTP — si se deja vacío, PHPMailer usa mail() de PHP internamente
        'smtp' => [
            'host'     => 'smtp.zoho.com',
            'port'     => 587,
            'secure'   => 'tls',     // 'ssl' o 'tls'
            'username' => 'hola@v11.com.ar',
            'password' => '***REMOVED***',
        ],
    ],
];
