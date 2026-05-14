<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class CertificadosController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function getDf(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cuit, razon_social, cert_pem, key_pem FROM datos_fiscales WHERE usuario_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function buildCsrConfig(): array
    {
        $cnf = tempnam(sys_get_temp_dir(), 'openssl_') . '.cnf';
        file_put_contents($cnf, "[req]\ndistinguished_name=req_dn\n[req_dn]\n");
        return [
            'config'           => $cnf,
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
    }

    public function estado(Request $request): void
    {
        $payload = $this->requireAuth($request);
        $df      = $this->getDf($payload['sub']);

        $certPem   = $df['cert_pem'] ?? '';
        $tieneKey  = ($df['key_pem'] ?? '') !== '';
        $tieneCert = $certPem !== '';

        $certVence = $certCN = null;
        if ($tieneCert) {
            $info = @openssl_x509_parse($certPem);
            if ($info) {
                $certVence = $info['validTo_time_t'] ?? null;
                $certCN    = $info['subject']['CN']   ?? null;
            }
        }

        Response::success([
            'cuit'        => $df['cuit']        ?? '',
            'razonSocial' => $df['razon_social'] ?? '',
            'tieneKey'    => $tieneKey,
            'tieneCsr'    => $tieneKey,
            'tieneCert'   => $tieneCert,
            'certVence'   => $certVence,
            'certCN'      => $certCN,
            'ahora'       => time(),
        ]);
    }

    public function generar(Request $request): void
    {
        $payload = $this->requireAuth($request);
        $df      = $this->getDf($payload['sub']);

        $cuit        = $df['cuit']        ?? '';
        $razonSocial = $df['razon_social'] ?? '';

        if (!$cuit || !$razonSocial) {
            Response::error('Completá el CUIT y la Razón Social antes de generar el certificado.');
        }

        $config = $this->buildCsrConfig();
        $dn = [
            'countryName'      => 'AR',
            'commonName'       => $razonSocial,
            'organizationName' => $razonSocial,
            'serialNumber'     => 'CUIT ' . preg_replace('/\D/', '', $cuit),
        ];

        $privKey = openssl_pkey_new($config);
        if (!$privKey) {
            @unlink($config['config']);
            Response::error('Error generando la clave privada: ' . openssl_error_string());
        }

        $csr = openssl_csr_new($dn, $privKey, $config);
        if (!$csr) {
            @unlink($config['config']);
            Response::error('Error generando el CSR: ' . openssl_error_string());
        }

        openssl_pkey_export($privKey, $keyPem, null, $config);
        openssl_csr_export($csr, $csrPem);
        @unlink($config['config']);

        $this->db->prepare(
            'INSERT INTO datos_fiscales (usuario_id, key_pem)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE key_pem = VALUES(key_pem)'
        )->execute([$payload['sub'], $keyPem]);

        Response::success(['csr' => $csrPem], 'Clave privada y CSR generados.');
    }

    public function csr(Request $request): void
    {
        $payload = $this->requireAuth($request);
        $df      = $this->getDf($payload['sub']);

        $keyPem      = $df['key_pem']      ?? '';
        $cuit        = $df['cuit']         ?? '';
        $razonSocial = $df['razon_social'] ?? '';

        if (!$keyPem) {
            Response::error('No hay clave privada generada. Ejecutá el paso 1 primero.');
        }

        $config  = $this->buildCsrConfig();
        $privKey = openssl_pkey_get_private($keyPem);

        if (!$privKey) {
            @unlink($config['config']);
            Response::error('No se pudo leer la clave privada almacenada.');
        }

        $dn = [
            'countryName'      => 'AR',
            'commonName'       => $razonSocial,
            'organizationName' => $razonSocial,
            'serialNumber'     => 'CUIT ' . preg_replace('/\D/', '', $cuit),
        ];

        $csr = openssl_csr_new($dn, $privKey, $config);
        @unlink($config['config']);

        if (!$csr) {
            Response::error('Error regenerando el CSR.');
        }

        openssl_csr_export($csr, $csrPem);
        Response::success(['csr' => $csrPem]);
    }

    public function subir(Request $request): void
    {
        $payload  = $this->requireAuth($request);
        $userId   = $payload['sub'];

        $certFile = $_FILES['cert'] ?? null;
        if (!$certFile || $certFile['error'] !== UPLOAD_ERR_OK) {
            Response::error('Seleccioná un archivo de certificado válido.');
        }

        $certContenido = file_get_contents($certFile['tmp_name']);
        $cert = @openssl_x509_read($certContenido);
        if (!$cert) {
            Response::error('El archivo no es un certificado PEM válido.');
        }

        $newKeyPem = null;
        $keyFile   = $_FILES['key'] ?? null;
        if ($keyFile && $keyFile['error'] === UPLOAD_ERR_OK) {
            $keyContenido = file_get_contents($keyFile['tmp_name']);
            if (!@openssl_pkey_get_private($keyContenido)) {
                Response::error('El archivo key.pem no es una clave privada válida.');
            }
            $newKeyPem = $keyContenido;
        }

        if ($newKeyPem !== null) {
            $this->db->prepare(
                'INSERT INTO datos_fiscales (usuario_id, cert_pem, key_pem)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE cert_pem = VALUES(cert_pem), key_pem = VALUES(key_pem)'
            )->execute([$userId, $certContenido, $newKeyPem]);
        } else {
            $this->db->prepare(
                'INSERT INTO datos_fiscales (usuario_id, cert_pem)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE cert_pem = VALUES(cert_pem)'
            )->execute([$userId, $certContenido]);
        }

        $info      = openssl_x509_parse($cert);
        $certVence = $info['validTo_time_t'] ?? null;
        $certCN    = $info['subject']['CN']   ?? null;

        Response::success([
            'tieneCert' => true,
            'certVence' => $certVence,
            'certCN'    => $certCN,
            'ahora'     => time(),
        ], 'Certificado instalado.' . ($newKeyPem ? ' Clave privada cargada.' : ''));
    }
}
