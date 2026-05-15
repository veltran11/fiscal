<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $cfg = self::getConfig();
            $db  = $cfg['db'];

            $dsn = "{$db['driver']}:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";

            try {
                self::$instance = new PDO($dsn, $db['username'], $db['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Error de conexión a la base de datos']);
                exit;
            }
        }

        return self::$instance;
    }

    private static function getConfig(): array
    {
        $cfg = require __DIR__ . '/../Config/config.php';
        $localFile = __DIR__ . '/../Config/config.local.php';
        if (file_exists($localFile)) {
            $local = require $localFile;
            $cfg = array_replace_recursive($cfg, $local);
        }
        return $cfg;
    }
}
