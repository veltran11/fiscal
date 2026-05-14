<?php

namespace App\Services;

class AfipService
{
    private const WSAA_PROD   = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl';
    private const WSAA_HOMO   = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';

    // Padrón A13 SOAP — aws.afip.gov.ar (distinto a awis que está caído)
    private const PADRON_PROD = 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl';
    private const PADRON_HOMO = 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl';

    // Factura Electrónica (wsfe) — para puntos de venta
    private const WSFE_PROD   = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';
    private const WSFE_HOMO   = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';

    private bool  $prod;
    private array $soapOpts;
    private array $sslCtxOpts = [
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['timeout' => 30],
    ];

    public function __construct(bool $produccion = false)
    {
        $this->prod     = $produccion;
        $this->soapOpts = [
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'stream_context'     => stream_context_create($this->sslCtxOpts),
        ];
    }

    // ── Ticket de acceso (WSAA SOAP) ─────────────────────────────────────────

    public function getTicket(string $certPem, string $keyPem, string $cuitNum, string $service = 'ws_sr_padron_a13'): array
    {
        $cacheFile = sys_get_temp_dir() . "/afip_ta_{$cuitNum}_{$service}.json";

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && ($cached['expiry'] ?? 0) > time() + 60) {
                return $cached;
            }
        }

        $tra    = $this->buildTra($service);
        $cms    = $this->signTra($tra, $certPem, $keyPem);
        $wsdl   = $this->wsdlLocal($this->prod ? self::WSAA_PROD : self::WSAA_HOMO);
        $client = new \SoapClient($wsdl, $this->soapOpts);
        $res    = $client->loginCms(['in0' => $cms]);

        $xml    = new \SimpleXMLElement($res->loginCmsReturn);
        $ticket = [
            'token'  => (string) $xml->credentials->token,
            'sign'   => (string) $xml->credentials->sign,
            'expiry' => strtotime((string) $xml->header->expirationTime),
        ];

        file_put_contents($cacheFile, json_encode($ticket));
        return $ticket;
    }

    // ── Padrón A13 (SOAP) ────────────────────────────────────────────────────

    public function getPadron(string $token, string $sign, string $cuit): array
    {
        $cuitNum = (int) preg_replace('/\D/', '', $cuit);
        $wsdl    = $this->wsdlLocal($this->prod ? self::PADRON_PROD : self::PADRON_HOMO);
        $client  = new \SoapClient($wsdl, $this->soapOpts);

        $res = $client->getPersona([
            'token'            => $token,
            'sign'             => $sign,
            'cuitRepresentada' => $cuitNum,
            'idPersona'        => $cuitNum,
        ]);

        $persona = $res->personaReturn->persona ?? null;
        if (!$persona) {
            throw new \RuntimeException('ARCA no devolvió datos para ese CUIT.');
        }

        return $this->parsePadron($persona);
    }

    private function parsePadron(object $p): array
    {
        $tipo  = strtoupper((string) ($p->tipoPersona ?? ''));
        $razon = $tipo === 'JURIDICA'
            ? (string) ($p->razonSocial ?? '')
            : trim((string) ($p->nombre ?? '') . ' ' . (string) ($p->apellido ?? ''));

        $doms = $p->domicilio ?? [];
        if (is_object($doms)) $doms = [$doms];

        $dom = null;
        foreach ($doms as $d) {
            if (strtoupper((string) ($d->tipoDomicilio ?? '')) === 'FISCAL') {
                $dom = $d;
                break;
            }
        }
        $dom = $dom ?? ($doms[0] ?? null);

        if ($dom && !empty((string) ($dom->direccion ?? ''))) {
            $dir = trim((string) $dom->direccion);
        } elseif ($dom) {
            $dir = trim(implode(' ', array_filter([
                (string) ($dom->calle  ?? ''),
                (string) ($dom->numero ?? ''),
                ($dom->piso ?? '') !== '' ? 'P' . $dom->piso : '',
                (string) ($dom->oficinaDptoLocal ?? ''),
            ])));
        } else {
            $dir = '';
        }

        $inicio = null;
        $f = (string) ($p->periodoActividadPrincipal ?? '');
        if (strlen($f) === 6) {
            $inicio = substr($f, 4, 2) . '/' . substr($f, 0, 4);
        }

        return [
            'razon_social'       => $razon,
            'domicilio_fiscal'   => $dir,
            'localidad'          => $dom ? (string) ($dom->localidad ?? $dom->descripcionProvincia ?? '') : '',
            'codigo_postal'      => $dom ? (string) ($dom->codigoPostal ?? '') : '',
            'inicio_actividades' => $inicio,
        ];
    }

    // ── Puntos de venta (wsfe SOAP) ───────────────────────────────────────────

    public function getPuntosVenta(string $token, string $sign, string $cuit): array
    {
        $cuitNum = (int) preg_replace('/\D/', '', $cuit);
        $wsdl    = $this->wsdlLocal($this->prod ? self::WSFE_PROD : self::WSFE_HOMO, 'wsfev1.wsdl');
        $client  = new \SoapClient($wsdl, $this->soapOpts);

        $res    = $client->FEParamGetPtosVenta([
            'Auth' => ['Token' => $token, 'Sign' => $sign, 'Cuit' => $cuitNum],
        ]);
        $result = $res->FEParamGetPtosVentaResult ?? null;
        if (!$result) return [];

        $pts = $result->ResultGet->PtoVenta ?? [];
        if (is_object($pts)) $pts = [$pts];

        $activos = [];
        foreach ($pts as $pt) {
            $baja = strtoupper(trim((string) ($pt->FchBaja ?? '')));
            if ($baja === '' || $baja === 'NULL') {
                $activos[] = (int) $pt->Nro;
            }
        }
        sort($activos);
        return $activos;
    }

    // ── WSAA helpers ─────────────────────────────────────────────────────────

    private function buildTra(string $service): string
    {
        $tz  = new \DateTimeZone('America/Argentina/Buenos_Aires');
        $gen = (new \DateTime('now', $tz))->modify('-10 minutes')->format('Y-m-d\TH:i:sP');
        $exp = (new \DateTime('now', $tz))->modify('+10 minutes')->format('Y-m-d\TH:i:sP');

        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<loginTicketRequest version="1.0"><header>'
             . '<uniqueId>' . time() . '</uniqueId>'
             . "<generationTime>{$gen}</generationTime>"
             . "<expirationTime>{$exp}</expirationTime>"
             . "</header><service>{$service}</service></loginTicketRequest>";
    }

    private function signTra(string $tra, string $certPem, string $keyPem): string
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'tra_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'cms_');

        file_put_contents($tmpIn, $tra);
        openssl_pkcs7_sign($tmpIn, $tmpOut, $certPem, $keyPem, [], PKCS7_BINARY | PKCS7_NOCHAIN);
        $raw = file_get_contents($tmpOut);

        @unlink($tmpIn);
        @unlink($tmpOut);

        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        return trim($parts[1] ?? $raw);
    }

    private function wsdlLocal(string $url, string $name = ''): string
    {
        if (!$name) {
            $name = basename(parse_url($url, PHP_URL_PATH)) . '.wsdl';
        }
        $local = __DIR__ . '/../../wsdl/' . $name;

        if (file_exists($local)) return $local;

        throw new \RuntimeException(
            "WSDL no encontrado en api/wsdl/{$name}. " .
            "Abrí {$url} en el navegador, guardá el contenido como \"{$name}\" " .
            "y copialo a api/wsdl/."
        );
    }
}
