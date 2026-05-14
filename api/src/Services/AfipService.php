<?php

namespace App\Services;

class AfipService
{
    private string $wsaaUrl;
    private string $padronUrl;
    private string $wsfeUrl;
    private string $cuit;
    private string $certPem;
    private string $keyPem;
    private bool   $prod;

    public function __construct(bool $produccion, string $cuit, string $certPem = '', string $keyPem = '')
    {
        $this->prod    = $produccion;
        $this->cuit    = preg_replace('/\D/', '', $cuit);
        $this->certPem = $certPem;
        $this->keyPem  = $keyPem;
        $this->wsaaUrl   = $produccion
            ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
        $this->padronUrl = 'file://' . __DIR__ . '/../../wsdl/personaServiceA13.wsdl';
        $this->wsfeUrl   = 'file://' . __DIR__ . '/../../wsdl/wsfev1.wsdl';
    }

    public function isReady(): bool
    {
        return $this->cuit !== '' && $this->certPem !== '' && $this->keyPem !== '';
    }

    // ── Cache TA ───────────────────────────────────────────────────────────

    private function cacheFile(string $service): string
    {
        return sys_get_temp_dir() . '/afip_ta_' . $this->cuit . '_' . $service . '.json';
    }

    private function getCachedTA(string $service): ?array
    {
        $f = $this->cacheFile($service);
        if (!file_exists($f)) return null;
        $d = json_decode(file_get_contents($f), true);
        if (!$d || ($d['expiry'] ?? 0) < time() + 60) return null;
        return $d;
    }

    private function saveTA(array $ta, string $service): void
    {
        file_put_contents($this->cacheFile($service), json_encode([
            'token'  => $ta['token'],
            'sign'   => $ta['sign'],
            'expiry' => $ta['expiry'] ?? (time() + 600),
        ]));
    }

    // ── WSAA ───────────────────────────────────────────────────────────────

    private function signTRA(string $tra): string
    {
        if (!$this->certPem || !$this->keyPem) {
            throw new \RuntimeException('Certificado o clave privada no configurados.');
        }

        $certFile = tempnam(sys_get_temp_dir(), 'afip_cert_');
        $keyFile  = tempnam(sys_get_temp_dir(), 'afip_key_');
        $traFile  = tempnam(sys_get_temp_dir(), 'afip_tra_');
        $cmsFile  = tempnam(sys_get_temp_dir(), 'afip_cms_');

        try {
            file_put_contents($certFile, $this->certPem);
            file_put_contents($keyFile,  $this->keyPem);
            file_put_contents($traFile, $tra);

            $ok = openssl_pkcs7_sign(
                $traFile,
                $cmsFile,
                'file://' . $certFile,
                ['file://' . $keyFile, ''],
                [],
                PKCS7_NOCHAIN | PKCS7_BINARY
            );
            if (!$ok) {
                throw new \RuntimeException('Error firmando TRA: ' . openssl_error_string());
            }

            $raw   = file_get_contents($cmsFile);
            $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
            if (count($parts) < 2 || trim($parts[1] ?? '') === '') {
                throw new \RuntimeException('No se pudo extraer el CMS del TRA.');
            }
            return str_replace(["\r", "\n", ' '], '', $parts[1]);
        } finally {
            @unlink($certFile);
            @unlink($keyFile);
            @unlink($traFile);
            @unlink($cmsFile);
        }
    }

    private function getTA(string $service): array
    {
        $ta = $this->getCachedTA($service);
        if ($ta) return $ta;

        $now     = time();
        $genTime = date('c', $now - 60);
        $expTime = date('c', $now + 600);
        $tra = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<loginTicketRequest version="1.0">'
             . '<header>'
             . "<uniqueId>{$now}</uniqueId>"
             . "<generationTime>{$genTime}</generationTime>"
             . "<expirationTime>{$expTime}</expirationTime>"
             . '</header>'
             . "<service>{$service}</service>"
             . '</loginTicketRequest>';

        $b64 = $this->signTRA($tra);

        $soap = '<?xml version="1.0" encoding="UTF-8"?>'
              . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
              . ' xmlns:ns1="http://wsaa.view.sua.dvadac.desein.afip.gov.ar">'
              . '<SOAP-ENV:Body>'
              . '<ns1:loginCms><ns1:in0>' . $b64 . '</ns1:in0></ns1:loginCms>'
              . '</SOAP-ENV:Body>'
              . '</SOAP-ENV:Envelope>';

        $ch = curl_init($this->wsaaUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soap,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=UTF-8',
                'SOAPAction: ""',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errmsg = curl_error($ch);
        curl_close($ch);

        if ($errno || !$resp) {
            throw new \RuntimeException('Error de red WSAA: ' . $errmsg);
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($resp);
        if ($xml === false) {
            throw new \RuntimeException('WSAA devolvió XML no válido.');
        }

        $faults = $xml->xpath('//*[local-name()="Fault"]');
        if (!empty($faults)) {
            $faultString = (string)($faults[0]->faultstring ?? $faults[0]->detail ?? 'Error desconocido');
            throw new \RuntimeException('Error WSAA AFIP: ' . trim($faultString));
        }

        $results = $xml->xpath('//*[local-name()="loginCmsReturn"]');
        if (empty($results)) {
            throw new \RuntimeException('Respuesta inesperada WSAA.');
        }

        $taObj = new \SimpleXMLElement((string)$results[0]);
        $ta = [
            'token'  => (string)$taObj->credentials->token,
            'sign'   => (string)$taObj->credentials->sign,
            'expiry' => strtotime((string)$taObj->header->expirationTime),
        ];
        $this->saveTA($ta, $service);
        return $ta;
    }

    // ── Padrón A13 ─────────────────────────────────────────────────────────

    public function getPadron(string $cuit): array
    {
        $cuitNum = preg_replace('/\D/', '', $cuit);
        $ta      = $this->getTA('ws_sr_padron_a13');

        try {
            $client = new \SoapClient($this->padronUrl, [
                'stream_context'     => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'exceptions'         => true,
                'connection_timeout' => 20,
            ]);

            $resp = $client->getPersona([
                'token'            => $ta['token'],
                'sign'             => $ta['sign'],
                'cuitRepresentada' => (int)$this->cuit,
                'idPersona'        => (int)$cuitNum,
            ]);

            $persona = $resp->personaReturn ?? null;
            if (!$persona) {
                throw new \RuntimeException('CUIT no encontrado en el padrón.');
            }

            return json_decode(json_encode($persona), true);
        } catch (\SoapFault $e) {
            $msg = $e->getMessage();
            if (preg_match('/no encontrad|sin resultado|0 result/i', $msg)) {
                throw new \RuntimeException('CUIT no encontrado en el padrón.');
            }
            throw new \RuntimeException('Error padrón A13: ' . $msg);
        }
    }

    // ── Puntos de venta ────────────────────────────────────────────────────

    public function getPuntosVenta(): array
    {
        $ta = $this->getTA('wsfe');

        try {
            $client = new \SoapClient($this->wsfeUrl, [
                'stream_context'     => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'exceptions'         => true,
                'connection_timeout' => 20,
            ]);

            $resp = $client->FEParamGetPtosVenta([
                'Auth' => [
                    'Token' => $ta['token'],
                    'Sign'  => $ta['sign'],
                    'Cuit'  => (int)$this->cuit,
                ],
            ]);

            $result = $resp->FEParamGetPtosVentaResult ?? null;
            if (!$result || empty($result->ResultGet)) return [];

            $items = $result->ResultGet->PtoVenta ?? [];
            if (is_object($items)) $items = [$items];

            $puntos = [];
            foreach ($items as $p) {
                $fchBaja = strtoupper(trim((string)($p->FchBaja ?? '')));
                if ($fchBaja !== '' && $fchBaja !== 'NULL') continue;
                $puntos[] = (int)$p->Nro;
            }
            sort($puntos);
            return $puntos;
        } catch (\SoapFault $e) {
            throw new \RuntimeException('Error WSFE puntos de venta: ' . $e->getMessage());
        }
    }

    // ── Último comprobante ────────────────────────────────────────────────

    public function getUltimoComprobante(int $ptoVta, int $cbteTipo = 11): int
    {
        $ta = $this->getTA('wsfe');

        try {
            $client = new \SoapClient($this->wsfeUrl, [
                'stream_context'     => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'exceptions'         => true,
                'connection_timeout' => 20,
            ]);

            $resp = $client->FECompUltimoAutorizado([
                'Auth' => [
                    'Token' => $ta['token'],
                    'Sign'  => $ta['sign'],
                    'Cuit'  => (int)$this->cuit,
                ],
                'PtoVta'   => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ]);

            $result = $resp->FECompUltimoAutorizadoResult ?? null;
            if ($result && !empty($result->Errors->Err)) {
                $err = $result->Errors->Err;
                $msg = is_array($err) ? $err[0]->Msg : $err->Msg;
                throw new \RuntimeException('WSFE: ' . $msg);
            }

            return (int)($result->CbteNro ?? 0);
        } catch (\SoapFault $e) {
            throw new \RuntimeException('Error WSFE último comprobante: ' . $e->getMessage());
        }
    }

    // ── Solicitar CAE ──────────────────────────────────────────────────────

    /**
     * Solicita CAE a AFIP/ARCA.
     *
     * @param int    $ptoVta    Punto de venta
     * @param int    $numero    Número de comprobante
     * @param string $fecha     Fecha YYYY-mm-dd
     * @param float  $monto     Monto total
     * @param int    $docTipo   Tipo doc (80=CUIT, 96=DNI)
     * @param int    $docNro    Nro de documento del cliente
     * @param int    $cbteTipo  Tipo comprobante (11=Factura C)
     * @param int    $concepto  1=producto, 2=servicio
     */
    public function solicitarCAE(
        int    $ptoVta,
        int    $numero,
        string $fecha,
        float  $monto,
        int    $docTipo,
        int    $docNro,
        int    $cbteTipo = 11,
        int    $concepto = 2
    ): array {
        $ta        = $this->getTA('wsfe');
        $fechaAfip = str_replace('-', '', $fecha);

        $detalle = [
            'Concepto'   => $concepto,
            'DocTipo'    => $docTipo,
            'DocNro'     => $docNro,
            'CbteDesde'  => $numero,
            'CbteHasta'  => $numero,
            'CbteFch'    => $fechaAfip,
            'ImpTotal'   => round($monto, 2),
            'ImpTotConc' => 0,
            'ImpNeto'    => round($monto, 2),
            'ImpOpEx'    => 0,
            'ImpIVA'     => 0,
            'ImpTrib'    => 0,
            'MonId'      => 'PES',
            'MonCotiz'   => 1,
        ];

        if ($concepto >= 2) {
            $detalle['FchServDesde'] = $fechaAfip;
            $detalle['FchServHasta'] = $fechaAfip;
            $detalle['FchVtoPago']   = date('Ymd', strtotime($fecha . ' +30 days'));
        }

        try {
            $client = new \SoapClient($this->wsfeUrl, [
                'stream_context'     => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]),
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'exceptions'         => true,
                'connection_timeout' => 20,
            ]);

            $resp = $client->FECAESolicitar([
                'Auth' => [
                    'Token' => $ta['token'],
                    'Sign'  => $ta['sign'],
                    'Cuit'  => (int)$this->cuit,
                ],
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg'  => 1,
                        'PtoVta'   => $ptoVta,
                        'CbteTipo' => $cbteTipo,
                    ],
                    'FeDetReq' => [
                        'FECAEDetRequest' => $detalle,
                    ],
                ],
            ]);

            $result = $resp->FECAESolicitarResult ?? null;

            if (!empty($result->Errors->Err)) {
                $err = $result->Errors->Err;
                $msg = is_array($err) ? $err[0]->Msg : $err->Msg;
                throw new \RuntimeException('Error AFIP: ' . $msg);
            }

            $det = $result->FeDetResp->FECAEDetResponse ?? null;
            if (!$det || (string)$det->Resultado === 'R') {
                $obs = '';
                if (!empty($det->Observaciones->Obs)) {
                    $o   = $det->Observaciones->Obs;
                    $obs = ' — ' . (is_array($o) ? $o[0]->Msg : $o->Msg);
                }
                throw new \RuntimeException('AFIP rechazó la factura' . $obs);
            }

            $vto = (string)$det->CAEFchVto;
            return [
                'cae'             => (string)$det->CAE,
                'cae_vencimiento' => substr($vto, 0, 4) . '-' . substr($vto, 4, 2) . '-' . substr($vto, 6, 2),
            ];
        } catch (\SoapFault $e) {
            throw new \RuntimeException('Error WSFE: ' . $e->getMessage());
        }
    }
}
