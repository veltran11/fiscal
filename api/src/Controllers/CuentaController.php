<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AfipService;
use PDO;

class CuentaController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function get(Request $request): void
    {
        $payload = $this->requireAuth($request);

        //datos_fiscales
        $stmt = $this->db->prepare(
            'SELECT cuit, razon_social, nombre_fantasia,
                    inicio_actividades, punto_venta,
                    domicilio_fiscal, if(trim(localidad) != "",localidad,provincia) localidad, codigo_postal,
                    (cert_pem IS NOT NULL AND cert_pem != "") AS tiene_cert,
                    (key_pem  IS NOT NULL AND key_pem  != "") AS tiene_key
             FROM datos_fiscales
             WHERE usuario_id = ?
             LIMIT 1'
        );
        $stmt->execute([$payload['sub']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        //ptos_vta
        $stmt = $this->db->prepare(
            'SELECT nro
             FROM ptos_vta
             WHERE usuario_id = ?'
        );
        $stmt->execute([$payload['sub']]);
        $ptos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        Response::success($row + ['puntos_venta' => $ptos]);
    }

    public function save(Request $request): void
    {
        $payload = $this->requireAuth($request);

        $campos = ['cuit', 'razon_social', 'nombre_fantasia', 'punto_venta'];
        $data   = [];
        foreach ($campos as $c) {
            $v = $request->body($c);
            if ($v !== null) $data[$c] = $v;
        }

        if (empty($data)) {
            Response::error('No hay datos para guardar.');
        }

        $set  = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $vals = array_values($data);

        $this->db->prepare(
            "INSERT INTO datos_fiscales (usuario_id, " . implode(', ', array_keys($data)) . ")
             VALUES (?, " . implode(', ', array_fill(0, count($data), '?')) . ")
             ON DUPLICATE KEY UPDATE $set"
        )->execute([$payload['sub'], ...$vals, ...$vals]);

        Response::success(null, 'Datos guardados.');
    }

    public function padron(Request $request): void
    {
        $payload = $this->requireAuth($request);
        $userId  = $payload['sub'];

        // Obtener certificado del usuario logueado
        $stmt = $this->db->prepare(
            'SELECT cuit, cert_pem, key_pem FROM datos_fiscales WHERE usuario_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $df = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // CUIT a consultar (puede venir del body o usar el del usuario)
        $cuit = $request->body('cuit') ?? '';
        if ($cuit !== '') {
            $cuit = preg_replace('/\D/', '', $cuit);
        }

        $cuitDueno     = $df['cuit'] ?? '';
        $certPem       = $df['cert_pem'] ?? '';
        $keyPem        = $df['key_pem'] ?? '';
        $cuitConsultar = $cuit ?: $cuitDueno;

        if (!$cuitConsultar) {
            Response::error('Ingresá un CUIT para consultar el padrón.');
        }
        if (!$certPem || !$keyPem) {
            Response::error('Instalá el certificado AFIP (pasos 1 y 3 en Certificados) antes de consultar.');
        }

        $cfg     = Config::get();
        $afip    = new AfipService($cfg['afip']['produccion']);
        $cuitNum = preg_replace('/\D/', '', $cuitDueno);

        try {
            $ticket = $afip->getTicket($certPem, $keyPem, $cuitNum, 'ws_sr_padron_a13');
            $datos  = $afip->getPadron($ticket['token'], $ticket['sign'], $cuitDueno, $cuitConsultar);
        } catch (\Throwable $e) {
            Response::error('Error consultando ARCA: ' . $e->getMessage());
        }

        if (empty($datos)) {
            Response::error('ARCA no devolvió datos para ese CUIT.');
        }

        Response::success($datos, 'Datos fiscales obtenidos de ARCA.');
    }
}
