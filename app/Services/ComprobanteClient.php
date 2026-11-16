<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Cliente HTTP del servicio de comprobantes de Quarkus (bo.cies.quarkus.comprobante). */
class ComprobanteClient
{
    private const MSG_CAIDO = 'El servicio de comprobantes no está disponible en este momento.';

    private function http()
    {
        return Http::baseUrl(config('services.quarkus.url'))
            ->withHeaders(['X-API-KEY' => config('services.quarkus.api_key')])
            ->timeout(config('services.quarkus.timeout'));
    }

    /**
     * POST /api/comprobantes. codEmp y codCaj van juntos: ninguno = anticipo pagado en línea.
     * $items: [['codSrv' => ..., 'cantidad' => int, 'precio' => float, 'descuento' => int], ...]
     * Devuelve: ['nroCom' => ..., 'monto' => ..., 'estado' => ..., 'fecha' => ...]
     */
    public function emitir(string $codCit, array $items, ?string $codEmp = null, ?string $codCaj = null): array
    {
        $ruta = '/api/comprobantes';
        try {
            $r = $this->http()->acceptJson()->post($ruta, [
                'codCit' => $codCit,
                'codEmp' => $codEmp,
                'codCaj' => $codCaj,
                'items'  => $items,
            ]);
        } catch (ConnectionException $e) {
            Log::channel('cies')->error("Quarkus no responde en {$ruta}: " . $e->getMessage());
            throw new DomainException(self::MSG_CAIDO);
        }

        if ($r->successful() && $r->json('nroCom')) {
            return $r->json();
        }

        $this->registrarFallo($ruta, $r);
        throw new DomainException($this->mensaje($r));
    }

    /** GET /api/comprobantes/{nro}/pdf. Devuelve los bytes del PDF. */
    public function pdf(string $nro): string
    {
        $ruta = '/api/comprobantes/' . rawurlencode($nro) . '/pdf';
        try {
            $r = $this->http()->accept('application/pdf')->get($ruta);
        } catch (ConnectionException $e) {
            Log::channel('cies')->error("Quarkus no responde en {$ruta}: " . $e->getMessage());
            throw new DomainException(self::MSG_CAIDO);
        }

        if ($r->successful()) {
            return $r->body();
        }

        $this->registrarFallo($ruta, $r);
        throw new DomainException($this->mensaje($r));
    }

    private function registrarFallo(string $ruta, Response $r): void
    {
        Log::channel('cies')->error("Quarkus respondió {$r->status()} en {$ruta}: " . mb_substr($r->body(), 0, 300));
    }

    private function mensaje(Response $r): string
    {
        if ($r->status() === 401 || $r->status() === 403 || $r->serverError()) {
            return self::MSG_CAIDO;
        }
        $error = $r->json('error');
        return is_string($error) && $error !== '' ? "Quarkus rechazó el comprobante: {$error}" : 'No se pudo generar el comprobante.';
    }
}
