<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Factura;
use App\Services\AfipService;
use PDO;

class FacturasController extends BaseController
{
    private Factura $model;
    private PDO $db;

    public function __construct()
    {
        $this->model = new Factura();
        $this->db    = Database::getInstance();
    }

    public function index(Request $request, array $params): void
    {
        $payload  = $this->requireAuth($request);
        $facturas = $this->model->allByUser((int) $payload['sub']);
        Response::success($facturas);
    }

    public function store(Request $request, array $params): void
    {
        $payload = $this->requireAuth($request);
        $userId  = (int) $payload['sub'];

        $clienteId = (int) $request->body('cliente_id');
        $fecha     = trim($request->body('fecha') ?? '');
        $concepto  = trim($request->body('concepto') ?? '');
        $monto     = (float) $request->body('monto');

        if (!$clienteId || !$fecha || !$concepto || $monto <= 0) {
            Response::error('Todos los campos son obligatorios.');
        }

        // ── Obtener datos del usuario (certificado, CUIT, punto de venta) ──
        $stmt = $this->db->prepare(
            'SELECT cuit, cert_pem, key_pem, punto_venta FROM datos_fiscales WHERE usuario_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $df = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cuitDueno = $df['cuit'] ?? '';
        $certPem   = $df['cert_pem'] ?? '';
        $keyPem    = $df['key_pem'] ?? '';
        $ptoVta    = (int) ($df['punto_venta'] ?? 0);

        if (!$certPem || !$keyPem) {
            Response::error('Instalá el certificado AFIP antes de facturar.');
        }
        if (!$ptoVta) {
            Response::error('Configurá el punto de venta en Mi cuenta antes de facturar.');
        }

        // ── Obtener datos del cliente ──
        $stmt = $this->db->prepare(
            'SELECT cuit, cond_iva_id FROM clientes WHERE id = ? AND usuario_id = ? LIMIT 1'
        );
        $stmt->execute([$clienteId, $userId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cuitCliente = preg_replace('/\D/', '', $cliente['cuit'] ?? '');
        if (strlen($cuitCliente) !== 11) {
            Response::error('El cliente no tiene un CUIT válido.');
        }

        $condIvaId = (int) ($cliente['cond_iva_id'] ?? 0);
        // Siempre usamos Factura C (11) para simplificar
        $tipoCbte = 11;

        // IVA: 21% sobre el monto
        $iva = round($monto * 21 / 121, 2);

        // ── Facturar en ARCA ──
        $cfg  = Config::get();
        $afip = new AfipService($cfg['afip']['produccion'], $cuitDueno, $certPem, $keyPem);

        if (!$afip->isReady()) {
            Response::error('Certificado AFIP no configurado correctamente.');
        }

        // ── Obtener último número de ARCA ──
        try {
            $ultimo = $afip->getUltimoComprobante($ptoVta, $tipoCbte);
        } catch (\Throwable $e) {
            error_log('FACTURAS: Error último comprobante: ' . $e->getMessage());
            $ultimo = 0;
        }
        $numero = $ultimo + 1;

        // ── Facturar en ARCA ──
        try {
            error_log('FACTURAS: facturando con numero=' . $numero . ' ptoVta=' . $ptoVta . ' tipoCbte=' . $tipoCbte);

            $caeData = $afip->solicitarCAE(
                $ptoVta,
                $numero,
                $fecha,
                $monto,
                80,             // docTipo: CUIT
                (int)$cuitCliente,
                $tipoCbte,
                2               // concepto: servicio
            );

            error_log('FACTURAS: CAE obtenido: ' . json_encode($caeData));
        } catch (\Throwable $e) {
            error_log('FACTURAS: Error en facturar: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            Response::error('Error al facturar en ARCA: ' . $e->getMessage());
        }

        // ── Guardar en base de datos ──
        $data = [
            'usuario_id'     => $userId,
            'cliente_id'     => $clienteId,
            'numero'         => $numero,
            'fecha'          => $fecha,
            'concepto'       => $concepto,
            'monto'          => $monto,
            'cae'            => $caeData['cae'],
            'cae_vencimiento' => $caeData['cae_vencimiento'] ?: null,
        ];

        $id = $this->model->create($data);
        $factura = $this->model->findForUser($id, $userId);
        Response::success($factura, 'Factura creada con CAE.');
    }

    public function update(Request $request, array $params): void
    {
        Response::error('Las facturas no se pueden modificar.', 400);
    }

    public function destroy(Request $request, array $params): void
    {
        Response::error('Las facturas no se pueden eliminar.', 400);
    }
}
