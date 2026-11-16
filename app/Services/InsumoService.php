<?php

namespace App\Services;

use App\Support\Firma;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * RF031 Recepción de insumos del paciente y RF032 Validación de entrega con firma, OTP y comprobante.
 * RN36: el insumo entra «Pendiente de verificación» y pasa a «Verificado» con la firma y el OTP del
 * entregante; ahí se emite el comprobante de entrega (Quarkus). Vencido o sin lote -> «Rechazado».
 * Eventos clínicos en DOM03; OTP rechazado en DOM02; fallo del comprobante en DOM03 (Alta).
 */
class InsumoService
{
    public const PENDIENTE  = 'Pendiente de verificación';
    public const VERIFICADO = 'Verificado';
    public const RECHAZADO  = 'Rechazado';

    private const PROPOSITO = 'ENTREGA_INSUMOS';

    public function __construct(private OtpClient $otp, private ComprobanteClient $comprobantes)
    {
    }

    // ---------- Consultas ----------

    public function medicamentos()
    {
        return DB::table('MEDICAMENTO')->orderBy('DES_MED')->get(['COD_MED', 'DES_MED']);
    }

    public function listar(string $estado = '', string $buscar = '')
    {
        $q = $this->base()
            ->select($this->columnas())
            ->orderByDesc('i.id')
            ->limit(100);

        if ($estado !== '') {
            $q->where('i.EST_INS', $estado);
        }
        $buscar = trim($buscar);
        if ($buscar !== '') {
            $q->where(function ($w) use ($buscar) {
                $w->where('i.COD_INS', $buscar)
                  ->orWhere('pa.CI_PAC', 'like', "{$buscar}%")
                  ->orWhere('pa.APE_PAC', 'like', "%{$buscar}%")
                  ->orWhere('en.CI_ENT', 'like', "{$buscar}%");
            });
        }

        return $q->get();
    }

    public function insumo(string $codIns): object
    {
        $i = $this->base()->where('i.COD_INS', $codIns)->first($this->columnas());
        if (!$i) {
            throw new DomainException('El insumo no existe.');
        }
        return $i;
    }

    /** RF016: comprobantes de insumos del paciente (solo verificados y con comprobante emitido). */
    public function comprobantesDelPaciente(string $codPac)
    {
        return DB::table('COMPROBANTE_ENTREGA as ce')
            ->join('INSUMO_TERCERO as i', 'i.COD_INS', '=', 'ce.COD_INS')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'i.COD_MED')
            ->join('ENTREGANTE as en', 'en.COD_ENT', '=', 'i.COD_ENT')
            ->where('i.COD_PAC', $codPac)->where('ce.EST_CEN', 'Emitido')
            ->orderByDesc('ce.FEC_CEN')
            ->get(['ce.NRO_CEN', 'ce.FEC_CEN', 'm.DES_MED', 'i.DOS_INS', 'i.CAN_INS', 'en.NOM_ENT', 'en.APE_ENT']);
    }

    public function comprobanteEsDelPaciente(string $nroCen, string $codPac): bool
    {
        return DB::table('COMPROBANTE_ENTREGA as ce')
            ->join('INSUMO_TERCERO as i', 'i.COD_INS', '=', 'ce.COD_INS')
            ->where('ce.NRO_CEN', $nroCen)->where('i.COD_PAC', $codPac)->exists();
    }

    // ---------- RF031: registro ----------

    /**
     * $d: ci_pac, ci_ent, nom_ent, ape_ent, cor_ent, tel_ent, cod_med, dosis, cantidad, lote, fecven
     * Devuelve el insumo creado (Pendiente de verificación o Rechazado).
     */
    public function registrar(array $d, string $codEmp): object
    {
        $pac = DB::table('PACIENTE')->where('CI_PAC', trim($d['ci_pac']))->first(['COD_PAC']);
        if (!$pac) {
            throw new DomainException('No hay un paciente registrado con ese CI.');
        }
        if (!DB::table('MEDICAMENTO')->where('COD_MED', $d['cod_med'])->exists()) {
            throw new DomainException('Elige un medicamento válido.');
        }

        $lote    = trim((string) ($d['lote'] ?? ''));
        $vence   = Carbon::createFromFormat('Y-m-d', $d['fecven'])->startOfDay();
        $motivos = [];
        if ($lote === '') {
            $motivos[] = 'sin número de lote';
        }
        if ($vence->lt(Carbon::today())) {
            $motivos[] = 'vencido el ' . $vence->format('d/m/Y');
        }
        $estado = $motivos ? self::RECHAZADO : self::PENDIENTE;

        return DB::transaction(function () use ($d, $codEmp, $pac, $lote, $vence, $estado, $motivos) {
            $codEnt = $this->entregante($d);

            $codIns = sprintf('INS%07d', (int) DB::table('INSUMO_TERCERO')->max('id') + 1);
            DB::table('INSUMO_TERCERO')->insert([
                'COD_INS'    => $codIns,
                'FEC_INS'    => Carbon::now()->toDateTimeString(),
                'DOS_INS'    => trim($d['dosis']),
                'CAN_INS'    => (int) $d['cantidad'],
                'LOT_INS'    => $lote === '' ? 'SIN LOTE' : $lote,
                'FECVEN_INS' => $vence->toDateString(),
                'EST_INS'    => $estado,
                'FIR_INS'    => null,
                'COD_PAC'    => $pac->COD_PAC,
                'COD_ENT'    => $codEnt,
                'COD_MED'    => $d['cod_med'],
                'COD_EMP'    => $codEmp,
                'COD_OTP'    => null,
            ]);

            if ($estado === self::RECHAZADO) {
                Auditoria::registrar('Insumo rechazado', 'Media', 'DOM03', 'INSUMO_TERCERO', $codIns,
                    'Insumo de ' . $d['nom_ent'] . ' ' . $d['ape_ent'] . ' rechazado: ' . implode(' y ', $motivos),
                    codPac: $pac->COD_PAC);
            } else {
                Auditoria::registrar('Recepción de insumo', 'Baja', 'DOM03', 'INSUMO_TERCERO', $codIns,
                    "Recibido de {$d['nom_ent']} {$d['ape_ent']} ({$codEnt}): {$d['cantidad']} u., lote {$lote}; pendiente de firma y OTP",
                    codPac: $pac->COD_PAC);
            }

            $insumo = DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->first();
            $insumo->MOTIVOS = $motivos;
            return $insumo;
        });
    }

    /** Busca al entregante por CI (y actualiza su contacto) o lo crea. */
    private function entregante(array $d): string
    {
        $datos = [
            'NOM_ENT' => trim($d['nom_ent']),
            'APE_ENT' => trim($d['ape_ent']),
            'COR_ENT' => trim($d['cor_ent']),
            'TEL_ENT' => trim($d['tel_ent']),
        ];

        $ent = DB::table('ENTREGANTE')->where('CI_ENT', trim($d['ci_ent']))->lockForUpdate()->first(['COD_ENT']);
        if ($ent) {
            DB::table('ENTREGANTE')->where('COD_ENT', $ent->COD_ENT)->update($datos);
            return $ent->COD_ENT;
        }

        $codEnt = sprintf('ENT%05d', (int) DB::table('ENTREGANTE')->max('id') + 1);
        DB::table('ENTREGANTE')->insert(['COD_ENT' => $codEnt, 'CI_ENT' => trim($d['ci_ent'])] + $datos);
        return $codEnt;
    }

    // ---------- RF032: OTP al familiar ----------

    /** Envía el OTP al entregante por correo o SMS (simulado) y lo deja asociado al insumo. */
    public function enviarOtp(string $codIns, string $canal, string $codUsr): array
    {
        $i = $this->insumo($codIns);
        if ($i->EST_INS !== self::PENDIENTE) {
            throw new DomainException("El insumo está «{$i->EST_INS}»; ya no se verifica.");
        }

        $canal   = $canal === 'SMS' ? 'SMS' : 'CORREO';
        $destino = $canal === 'SMS' ? $i->TEL_ENT : $i->COR_ENT;

        try {
            $r = $this->otp->generar($codUsr, self::PROPOSITO, $canal, $destino);
        } catch (DomainException $e) {
            Auditoria::registrar('Fallo envio OTP', 'Media', 'DOM02', 'INSUMO_TERCERO', $codIns,
                "OTP de entrega al familiar ({$canal}): {$e->getMessage()}", codPac: $i->COD_PAC);
            throw $e;
        }

        DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->where('EST_INS', self::PENDIENTE)
            ->update(['COD_OTP' => $r['codOtp']]);

        Auditoria::registrar('Envio OTP entrega', 'Baja', 'DOM02', 'OTP', $r['codOtp'],
            "OTP del insumo {$codIns} enviado por {$canal} a " . $this->enmascarar($canal, $destino), codPac: $i->COD_PAC);

        return [
            'codOtp'   => $r['codOtp'],
            'canal'    => $canal,
            'destino'  => $this->enmascarar($canal, $destino),
            'segundos' => (int) ($r['segundosValidez'] ?? 300),
        ];
    }

    // ---------- RF032: firma + OTP -> Verificado -> comprobante ----------

    /**
     * $firma: data URL PNG del canvas. Valida el OTP fuera de la transacción (HTTP), guarda la
     * firma, pasa el insumo a Verificado y, ya fuera de la transacción, pide el comprobante.
     * Devuelve ['NRO_CEN' => ?string].
     */
    public function verificar(string $codIns, string $firma, string $codigo, string $codUsr, string $codEmp): array
    {
        $i = $this->insumo($codIns);
        if ($i->EST_INS !== self::PENDIENTE) {
            throw new DomainException("El insumo está «{$i->EST_INS}»; ya no se verifica.");
        }
        if (!$i->COD_OTP) {
            throw new DomainException('Primero envía el código de verificación al familiar.');
        }

        $png = Firma::decodificar($firma, 'el familiar');

        $res = $this->otp->validar($i->COD_OTP, trim($codigo));
        if (!$res['valido']) {
            Auditoria::registrar('OTP rechazado', 'Media', 'DOM02', 'OTP', $i->COD_OTP,
                "Entrega del insumo {$codIns}: {$res['motivo']}", codPac: $i->COD_PAC);
            throw new DomainException(OtpClient::mensajeMotivo($res));
        }

        $ruta = Firma::guardar($png, "firmas/insumos/{$codIns}.png");

        DB::transaction(function () use ($codIns, $codUsr, $ruta) {
            $i = DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->lockForUpdate()->first();
            if (!$i || $i->EST_INS !== self::PENDIENTE) {
                throw new DomainException('El insumo ya no está pendiente de verificación.');
            }

            // Defensa en profundidad: el OTP del insumo debe estar Verificado, ser de entrega y de este enfermero
            $otp = DB::table('OTP')->where('COD_OTP', $i->COD_OTP)->first();
            if (!$otp || $otp->EST_OTP !== 'Verificado' || $otp->PRO_OTP !== self::PROPOSITO || $otp->COD_USR !== $codUsr) {
                throw new DomainException('El código de verificación no es válido para esta entrega.');
            }

            DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->update([
                'EST_INS' => self::VERIFICADO,
                'FIR_INS' => $ruta,
            ]);

            Auditoria::registrar('Insumo verificado', 'Media', 'DOM03', 'INSUMO_TERCERO', $codIns,
                "Entrega verificada con firma ({$ruta}) y OTP {$i->COD_OTP}", codPac: $i->COD_PAC);
        });

        return ['NRO_CEN' => $this->emitirComprobante($codIns, $codEmp)];
    }

    /**
     * Pide a Quarkus el comprobante de entrega. Siempre después del commit. Quarkus no duplica:
     * si ya hay uno Emitido lo devuelve. Si falla, se audita y queda el botón de reintento.
     */
    public function emitirComprobante(string $codIns, string $codEmp): ?string
    {
        if (DB::transactionLevel() > 0) {
            throw new LogicException('El comprobante de entrega se pide después del commit.');
        }

        $i = $this->insumo($codIns);
        if ($i->EST_INS !== self::VERIFICADO) {
            throw new DomainException('Solo un insumo Verificado tiene comprobante de entrega (RN36).');
        }
        if ($i->NRO_CEN) {
            return $i->NRO_CEN;
        }

        try {
            $r = $this->comprobantes->emitirEntrega($codIns, $codEmp);
        } catch (DomainException $e) {
            Log::channel('cies')->warning("Comprobante de entrega no emitido para {$codIns}: {$e->getMessage()}");
            Auditoria::registrar('Comprobante fallido', 'Alta', 'DOM03', 'INSUMO_TERCERO', $codIns,
                "Entrega verificada sin comprobante: {$e->getMessage()} Queda pendiente de reintento.", codPac: $i->COD_PAC);
            return null;
        }

        if (empty($r['yaExistia'])) {
            Auditoria::registrar('Comprobante entrega', 'Baja', 'DOM03', 'COMPROBANTE_ENTREGA', $r['nroCen'],
                "Comprobante de entrega del insumo {$codIns}", codPac: $i->COD_PAC);
        }

        return $r['nroCen'];
    }

    public function pdfEntrega(string $nroCen): string
    {
        return $this->comprobantes->pdfEntrega($nroCen);
    }

    public function firma(string $codIns): ?string
    {
        return Firma::leer(DB::table('INSUMO_TERCERO')->where('COD_INS', $codIns)->value('FIR_INS'));
    }

    // ---------- Apoyo ----------

    private function base()
    {
        return DB::table('INSUMO_TERCERO as i')
            ->join('PACIENTE as pa', 'pa.COD_PAC', '=', 'i.COD_PAC')
            ->join('ENTREGANTE as en', 'en.COD_ENT', '=', 'i.COD_ENT')
            ->join('MEDICAMENTO as m', 'm.COD_MED', '=', 'i.COD_MED')
            ->leftJoin('COMPROBANTE_ENTREGA as ce', function ($j) {
                $j->on('ce.COD_INS', '=', 'i.COD_INS')->where('ce.EST_CEN', '=', 'Emitido');
            });
    }

    private function columnas(): array
    {
        return ['i.COD_INS', 'i.FEC_INS', 'i.DOS_INS', 'i.CAN_INS', 'i.LOT_INS', 'i.FECVEN_INS', 'i.EST_INS',
                'i.FIR_INS', 'i.COD_OTP', 'i.COD_PAC', 'pa.NOM_PAC', 'pa.APE_PAC', 'pa.CI_PAC',
                'en.COD_ENT', 'en.NOM_ENT', 'en.APE_ENT', 'en.CI_ENT', 'en.COR_ENT', 'en.TEL_ENT',
                'm.DES_MED', 'ce.NRO_CEN'];
    }

    private function enmascarar(string $canal, string $destino): string
    {
        if ($canal === 'SMS') {
            return str_repeat('•', 4) . substr($destino, -4);
        }
        [$u, $dom] = array_pad(explode('@', $destino, 2), 2, '');
        return mb_substr($u, 0, 2) . '•••@' . $dom;
    }
}
