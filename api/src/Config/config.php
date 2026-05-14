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
];
