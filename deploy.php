<?php

/**
 * Script de deploy por FTP
 *
 * Configura los datos abajo y ejecutá:
 *   php deploy.php
 *
 * Sube solo los archivos que git tiene como modificados o nuevos.
 */

// ─── CONFIGURACIÓN ───────────────────────────────────────────────
$config = [
    'host'     => 'tudominio.com',
    'username' => 'tu_usuario_ftp',
    'password' => 'tu_contraseña',
    'port'     => 21,
    'remote_root' => '/public_html',
    'passive'  => true,
];

// ─── NO TOCAR DE ACÁ PARA ABAJO ──────────────────────────────────

function listChangedFiles(): array {
    $files = [];

    // Archivos modificados (M) y no trackeados (?)

    $output = shell_exec('git status --short 2>nul');
    if (!$output) {
        echo "⚠ No se pudo ejecutar git. Asegurate de estar en un repositorio git.\n";
        exit(1);
    }

    foreach (explode("\n", trim($output)) as $line) {
        if (preg_match('/^[M\?A]\s+(.+)$/', $line, $m)) {
            $path = trim($m[1]);
            // Ignorar vendor y node_modules
            if (str_contains($path, 'vendor/') || str_contains($path, 'node_modules/')) continue;
            $files[] = $path;
        }
    }

    return $files;
}

function ensureDir(FTP\Connection $conn, string $dir): void {
    $parts = explode('/', trim($dir, '/'));
    $path = '';
    foreach ($parts as $part) {
        $path .= '/' . $part;
        @ftp_mkdir($conn, $path);
    }
}

$files = listChangedFiles();

if (empty($files)) {
    echo "✅ No hay cambios para subir.\n";
    exit(0);
}

echo "====================================\n";
echo "  Deploy a {$config['host']}\n";
echo "====================================\n\n";
echo "Archivos a subir:\n";
foreach ($files as $f) echo "  - $f\n";
echo "\n";

$conn = ftp_connect($config['host'], $config['port']);
if (!$conn) die("❌ No se pudo conectar\n");

if (!ftp_login($conn, $config['username'], $config['password']))
    die("❌ Error de autenticación\n");

ftp_pasv($conn, $config['passive']);

echo "✅ Conectado\n\n";

$ok = 0;
$err = 0;

foreach ($files as $file) {
    $local  = __DIR__ . '/' . $file;
    $remote = $config['remote_root'] . '/' . $file;

    if (!file_exists($local)) {
        echo "⚠ No existe localmente: $file\n";
        continue;
    }

    ensureDir($conn, dirname($remote));

    echo "📄 $file ... ";
    if (ftp_put($conn, $remote, $local, FTP_BINARY)) {
        echo "✅\n";
        $ok++;
    } else {
        echo "❌\n";
        $err++;
    }
}

ftp_close($conn);

echo "\n====================================\n";
echo "  Resumen: $ok subidos, $err errores\n";
echo "====================================\n";

if ($err > 0) {
    exit(1);
}
