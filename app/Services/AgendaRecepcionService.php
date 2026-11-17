<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgendaRecepcionService
{
    public const ESPECIALIDADES = [
        'ESP0001' => 'SRV00001',
        'ESP0002' => 'SRV90001',
        'ESP0003' => 'SRV90002',
        'ESP0004' => 'SRV90003',
        'ESP0005' => 'SRV90004',
        'ESP0006' => 'SRV90005',
    ];

    public function __construct(private CitaService $citas) {}

    public function opciones(string $paciente): Collection
    {
        $permitidas = collect(['ESP0001']);
        $atencionGeneral = DB::table('CONSULTA as c')->join('CITA as ci', 'ci.COD_CIT', '=', 'c.COD_CIT')
            ->where('ci.COD_PAC', $paciente)->where('ci.EST_CIT', 'Atendida')->where('ci.COD_SRV', self::ESPECIALIDADES['ESP0001'])->exists();
        if ($atencionGeneral) {
            $derivaciones = DB::table('INTERCONSULTA as i')->join('CONSULTA as c', 'c.COD_CNS', '=', 'i.COD_CNS')
                ->join('HISTORIA_CLINICA as h', 'h.COD_HIS', '=', 'c.COD_HIS')
                ->join('EMPLEADO as e', 'e.COD_EMP', '=', 'i.COD_EMP')->where('h.COD_PAC', $paciente)
                ->whereIn('i.ESTADO', ['Pendiente', 'Respondida'])->whereNotNull('e.COD_ESP')->pluck('e.COD_ESP');
            $especializadas = $derivaciones->filter(fn ($codigo) => $codigo !== 'ESP0001' && isset(self::ESPECIALIDADES[$codigo]))->unique();
            if ($especializadas->isNotEmpty()) {
                $permitidas = $especializadas;
            }
        }
        return DB::table('ESPECIALIDAD')->whereIn('COD_ESP', $permitidas->intersect(array_keys(self::ESPECIALIDADES))->all())
            ->orderBy('DES_ESP')->get()->map(function ($especialidad) {
                $codigoServicio = self::ESPECIALIDADES[$especialidad->COD_ESP];
                $servicio = DB::table('SERVICIO')->where('COD_SRV', $codigoServicio)->first(['COD_SRV', 'NOM_SRV', 'PRE_SRV']);
                return $servicio ? ['codigo' => $especialidad->COD_ESP, 'nombre' => $especialidad->DES_ESP, 'servicio' => $servicio->COD_SRV, 'precio' => $servicio->PRE_SRV] : null;
            })->filter()->values();
    }

    public function reservar(string $paciente, string $especialidad, string $fecha, string $hora, string $motivo): object
    {
        if (!isset(self::ESPECIALIDADES[$especialidad])) {
            throw new DomainException('Especialidad no disponible.');
        }
        return DB::transaction(function () use ($paciente, $especialidad, $fecha, $hora, $motivo) {
            $fila = DB::table('ESPECIALIDAD')->where('COD_ESP', $especialidad)->lockForUpdate()->first();
            if (!$fila || !$this->opciones($paciente)->contains('codigo', $especialidad)) {
                throw new DomainException('Esta especialidad requiere una consulta general y una derivación médica registrada.');
            }
            $medicos = DB::table('EMPLEADO as e')->join('USUARIO as u', 'u.COD_EMP', '=', 'e.COD_EMP')
                ->where('e.COD_ESP', $especialidad)->where('u.COD_ROL', 'ROL003')->where('u.EST_USR', 'Activo')
                ->select('e.COD_EMP')->distinct()->orderBy('e.COD_EMP')->pluck('e.COD_EMP')->values()->all();
            if (!$medicos) {
                throw new DomainException('No hay médicos activos registrados para esta especialidad.');
            }
            $ultimo = DB::table('CITA as c')->join('EMPLEADO as e', 'e.COD_EMP', '=', 'c.COD_EMP')
                ->where('e.COD_ESP', $especialidad)->where('c.COD_SRV', self::ESPECIALIDADES[$especialidad])
                ->orderByDesc('c.id')->value('c.COD_EMP');
            $posicion = array_search($ultimo, $medicos, true);
            $inicio = $posicion === false ? 0 : ($posicion + 1) % count($medicos);
            for ($i = 0; $i < count($medicos); $i++) {
                $codigo = $medicos[($inicio + $i) % count($medicos)];
                if (collect($this->citas->horariosLibres($codigo, Carbon::parse($fecha)))->contains('hora', $hora)) {
                    try {
                        return $this->citas->reservar($paciente, $codigo, $fecha, $hora, self::ESPECIALIDADES[$especialidad], $motivo);
                    } catch (DomainException $e) {
                        if ($e->getMessage() !== 'El consultorio ya está ocupado a esa hora. Elige otro horario.') {
                            throw $e;
                        }
                    }
                }
            }
            throw new DomainException('Ningún médico de esa especialidad tiene disponible la hora solicitada. Elige otra fecha u hora.');
        });
    }
}
