<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MedicoService
{
    public function empleado(): string
    {
        $codigo = auth()->user()->COD_EMP;
        abort_unless($codigo, 403, 'Tu cuenta no está vinculada a un médico.');

        return $codigo;
    }

    public function pacientes(): Builder
    {
        $emp = $this->empleado();

        return DB::table('PACIENTE as p')->where(function ($q) use ($emp) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('CITA as c')
                ->whereColumn('c.COD_PAC', 'p.COD_PAC')->where('c.COD_EMP', $emp)
                ->whereNotIn('c.EST_CIT', ['Cancelada', 'Expirada', 'Pendiente de pago']))
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('INTERNACION as i')
                    ->whereColumn('i.COD_PAC', 'p.COD_PAC')->where('i.COD_EMP', $emp))
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('INTERCONSULTA as i')
                    ->join('CONSULTA as c', 'c.COD_CNS', '=', 'i.COD_CNS')
                    ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
                    ->whereColumn('h.COD_PAC', 'p.COD_PAC')->where('i.COD_EMP', $emp));
        });
    }

    public function paciente(string $codigo): object
    {
        $p = DB::table('PACIENTE')->where('COD_PAC', $codigo)->first();
        abort_unless($p, 404);
        $normal = $this->pacientes()->where('p.COD_PAC', $codigo)->exists();
        $permiso = session('medico_emergencia.'.$codigo);
        abort_unless($normal || ($permiso && $permiso > now()->timestamp), 403,
            'Este expediente no está asignado a tu atención. Usa el acceso de emergencia con justificación.');

        return $p;
    }

    public function historia(string $paciente): object
    {
        $this->paciente($paciente);

        return DB::transaction(function () use ($paciente) {
            DB::table('PACIENTE')->where('COD_PAC', $paciente)->lockForUpdate()->first();
            $h = DB::table('HISTORIA_CLINICA')->where('COD_PAC', $paciente)->orderBy('id')->first();
            if (! $h) {
                $codigo = $this->codigo('HISTORIA_CLINICA', 'COD_HIS', 'HIS', 6);
                DB::table('HISTORIA_CLINICA')->insert([
                    'COD_HIS' => $codigo, 'NRO_HIS' => 'H'.$codigo,
                    'FECAPE_HIS' => today(), 'EST_HIS' => 'Activa', 'COD_PAC' => $paciente,
                ]);
                $this->auditar('Crear', 'HISTORIA_CLINICA', $codigo, $paciente, 'Apertura de expediente');
                $h = DB::table('HISTORIA_CLINICA')->where('COD_HIS', $codigo)->first();
            }

            return $h;
        });
    }

    public function consulta(string $codigo, bool $propia = false): object
    {
        $c = DB::table('CONSULTA as c')->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
            ->where('c.COD_CNS', $codigo)->select('c.*', 'h.COD_PAC')->first();
        abort_unless($c, 404);
        $this->paciente($c->COD_PAC);
        if ($propia) {
            abort_unless($c->COD_EMP === $this->empleado(), 403, 'Solo el autor puede emitir indicaciones sobre esta consulta.');
        }

        return $c;
    }

    public function codigo(string $tabla, string $campo, string $prefijo, int $digitos = 7): string
    {
        do {
            $codigo = $prefijo.str_pad((string) random_int(1, (10 ** $digitos) - 1), $digitos, '0', STR_PAD_LEFT);
        } while (DB::table($tabla)->where($campo, $codigo)->exists());

        return $codigo;
    }

    public function auditar(string $accion, string $tabla, string $codigo, string $paciente, string $detalle): void
    {
        Auditoria::registrar($accion, 'Media', 'DOM03', $tabla, $codigo, $detalle, codPac: $paciente);
    }

    public function examenes(): Builder
    {
        return DB::table('SERVICIO as s')->join('CLASIFICACION_SERVICIO as c', 'c.COD_CLA', '=', 's.COD_CLA')
            ->where(function ($q) {
                $q->where('c.DES_CLA', 'like', '%Laboratorio%')->orWhere('c.DES_CLA', 'like', '%Imagen%')
                    ->orWhere('c.DES_CLA', 'like', '%Diagn%');
            })->select('s.*')->orderBy('s.NOM_SRV');
    }
}
