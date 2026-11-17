<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * RN15: la firma digital es la firma manuscrita trazada en pantalla. Se guarda como imagen PNG
 * en el almacenamiento privado (storage/app/private) y en la BD solo se registra la ruta.
 * Se usa en el pago del anticipo (FIR_PAG, AJ02) y en la entrega de insumos (FIR_INS).
 */
class Firma
{
    private const MAX_BYTES = 300_000;

    /** Valida el data URL del lienzo y devuelve los bytes PNG. No toca el disco. */
    public static function decodificar(string $dataUrl, string $quien = 'la persona'): string
    {
        if (!str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new DomainException("Falta la firma de {$quien}.");
        }
        $png = base64_decode(substr($dataUrl, 22), true);
        if ($png === false || strlen($png) < 200 || strlen($png) > self::MAX_BYTES
            || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new DomainException("La firma no es válida. Pide a {$quien} que firme de nuevo.");
        }
        $imagen = @getimagesizefromstring($png);
        if (!$imagen || ($imagen[2] ?? null) !== IMAGETYPE_PNG || $imagen[0] < 32 || $imagen[1] < 16
            || !str_ends_with($png, "\x00\x00\x00\x00IEND\xaeB\x60\x82")) {
            throw new DomainException("La firma no es una imagen PNG válida. Pide a {$quien} que firme de nuevo.");
        }
        return $png;
    }

    /** Guarda la imagen en el disco privado y devuelve la ruta que va a la BD. */
    public static function guardar(string $png, string $ruta): string
    {
        if (!Storage::disk('local')->put($ruta, $png)) {
            throw new DomainException('No se pudo guardar la firma. Intenta nuevamente.');
        }
        return $ruta;
    }

    public static function leer(?string $ruta): ?string
    {
        return $ruta && Storage::disk('local')->exists($ruta) ? Storage::disk('local')->get($ruta) : null;
    }
}
