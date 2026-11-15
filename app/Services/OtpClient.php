<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpClient
{
    private const MSG_CAIDO = 'Servicio de verificación no disponible. Intenta de nuevo en unos minutos.';

    private function http()
    {
        return Http::baseUrl(config('services.quarkus.url'))
            ->withHeaders(['X-API-KEY' => config('services.quarkus.api_key')])
            ->timeout(config('services.quarkus.timeout'))
            ->acceptJson();
    }

    /**
     * Genera y envía un OTP.
     * Devuelve: ['codOtp' => ..., 'expira' => ..., 'canal' => ..., 'segundosValidez' => int]
     */
    public function generar(string $codUsr, string $proposito, string $canal, string $destino): array
    {
        $r = $this->post('/api/otp/generar', [
            'codUsr'    => $codUsr,
            'proposito' => $proposito,
            'destino'   => $destino,
            'canal'     => $canal,
        ]);

        if ($r->failed()) {
            throw new DomainException($this->mensajeError($r, 'No se pudo generar el código de verificación.'));
        }

        return $r->json();
    }

    /**
     * Valida un OTP.
     * Devuelve: ['valido' => bool, 'motivo' => ?string, 'intentosRestantes' => int]
     * Lanza DomainException solo si Quarkus no responde o falla (nunca deja pasar).
     */
    public function validar(string $codOtp, string $codigo): array
    {
        $r = $this->post('/api/otp/validar', [
            'codOtp' => $codOtp,
            'codigo' => $codigo,
        ]);

        if ($r->successful() && is_array($r->json()) && array_key_exists('valido', $r->json())) {
            return [
                'valido'            => (bool) $r->json('valido'),
                'motivo'            => $r->json('motivo'),
                'intentosRestantes' => (int) ($r->json('intentosRestantes') ?? 0),
            ];
        }

        // 4xx/5xx o cuerpo raro: se trata como NO validado
        throw new DomainException($this->mensajeError($r, 'No se pudo verificar el código.'));
    }

    private function post(string $ruta, array $body): Response
    {
        try {
            $r = $this->http()->post($ruta, $body);
        } catch (ConnectionException $e) {
            Log::channel('cies')->error("Quarkus no responde en {$ruta}: " . $e->getMessage());
            throw new DomainException(self::MSG_CAIDO);
        }

        if ($r->status() === 401 || $r->status() === 403) {
            Log::channel('cies')->error("Quarkus rechazó la API key en {$ruta}");
            throw new DomainException(self::MSG_CAIDO);
        }
        if ($r->serverError()) {
            Log::channel('cies')->error("Quarkus error {$r->status()} en {$ruta}: " . $r->body());
            throw new DomainException(self::MSG_CAIDO);
        }

        return $r;
    }

    private function mensajeError(Response $r, string $defecto): string
    {
        $j = $r->json();
        if (is_array($j)) {
            foreach (['mensaje', 'motivo', 'error', 'message'] as $k) {
                if (!empty($j[$k]) && is_string($j[$k])) {
                    return $j[$k];
                }
            }
        }
        return $defecto;
    }
        /** Mensaje para el usuario a partir de la respuesta de validar(). */
    public static function mensajeMotivo(array $res): string
    {
        return match ($res['motivo'] ?? '') {
            'CODIGO_INCORRECTO' => 'Código incorrecto. Te quedan ' . ($res['intentosRestantes'] ?? 0) . ' intento(s).',
            'BLOQUEADO'         => 'Superaste los 3 intentos. Solicita un código nuevo.',
            'EXPIRADO'          => 'El código venció (dura 5 minutos). Solicita uno nuevo.',
            'YA_USADO'          => 'Ese código ya fue utilizado. Solicita uno nuevo.',
            default             => 'Código no válido. Solicita uno nuevo.',
        };
    }
}