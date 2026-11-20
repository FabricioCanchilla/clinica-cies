@extends('layouts.admin')

@section('titulo', 'Atender cita ' . $cita->COD_CIT)
@section('encabezado', 'Portal Médico · Atención')

@push('head')
    @include('medico._tailwind')
@endpush

@section('contenido')
<div class="vista-medico">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <h1 class="text-2xl font-bold text-emerald-800 mb-1">Cita {{ $cita->COD_CIT }}</h1>
            <p class="text-sm text-slate-600 mb-4">
                {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} · {{ substr($cita->HOR_CIT, 0, 5) }} · {{ $cita->NOM_SRV }}<br>
                <strong>Paciente:</strong> {{ $cita->NOM_PAC }} {{ $cita->APE_PAC }} (CI {{ $cita->CI_PAC }}, {{ \Carbon\Carbon::parse($cita->FECNAC_PAC)->age }} años, {{ $cita->SEX_PAC === 'F' ? 'femenino' : 'masculino' }})<br>
                <strong>Motivo:</strong> {{ $cita->MOT_CIT }}
            </p>


            <form method="POST" action="{{ route('medico.atender.guardar', $cita->COD_CIT) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="cie">Código CIE-10 <span class="text-red-600">*</span></label>
                        <input id="cie" name="cie" list="cie-lista" required autocomplete="off" value="{{ old('cie') }}"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 font-mono" placeholder="Busca: J06, gastritis…">
                        <datalist id="cie-lista"></datalist>
                        <p id="cie-desc" class="text-xs text-slate-500 mt-1">RN43: la consulta no se cierra sin un diagnóstico CIE-10.</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold mb-1" for="diagnostico">Diagnóstico</label>
                        <input id="diagnostico" name="diagnostico" maxlength="200" required value="{{ old('diagnostico') }}"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="Se completa con el CIE-10; puedes precisarlo">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="plan">Plan de tratamiento</label>
                    <textarea id="plan" name="plan" maxlength="255" required rows="3"
                              class="w-full border border-slate-300 rounded-lg px-3 py-2">{{ old('plan') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="evolucion">Nota de evolución</label>
                    <textarea id="evolucion" name="evolucion" maxlength="500" required rows="4"
                              class="w-full border border-slate-300 rounded-lg px-3 py-2">{{ old('evolucion') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">RF020: las notas de evolución no se eliminan; las correcciones se hacen con una nota nueva (adenda).</p>
                </div>
                <div class="border-t border-slate-200 pt-4">
                    <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-allergies text-red-500"></i> Alergia detectada en esta consulta (opcional)</h2>
                    <div class="grid grid-cols-3 gap-3">
                        <input name="alergia_des" maxlength="60" value="{{ old('alergia_des') }}" placeholder="Ej. Penicilina"
                               class="col-span-2 border border-slate-300 rounded-lg px-3 py-2">
                        <select name="alergia_sev" class="border border-slate-300 rounded-lg px-3 py-2">
                            <option value="">Severidad</option>
                            @foreach (['Leve', 'Moderada', 'Grave'] as $sev)
                                <option value="{{ $sev }}" @selected(old('alergia_sev') === $sev)>{{ $sev }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-prescription text-emerald-600"></i> Receta (RF018, opcional)</h2>
                    <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-slate-500 mb-1">
                        <div class="col-span-4">Medicamento</div><div class="col-span-2">Dosis</div><div class="col-span-2">Vía</div>
                        <div class="col-span-2">Cada (h)</div><div class="col-span-2">Días</div>
                    </div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <select name="items[{{ $i }}][med]" class="col-span-4 border border-slate-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">—</option>
                                @foreach ($medicamentos as $m)
                                    <option value="{{ $m->COD_MED }}" @selected(old("items.$i.med") === $m->COD_MED)>{{ $m->DES_MED }}</option>
                                @endforeach
                            </select>
                            <input name="items[{{ $i }}][dosis]" maxlength="20" value="{{ old("items.$i.dosis") }}" placeholder="1 tableta"
                                   class="col-span-2 border border-slate-300 rounded-lg px-2 py-2 text-sm">
                            <select name="items[{{ $i }}][via]" class="col-span-2 border border-slate-300 rounded-lg px-2 py-2 text-sm">
                                @foreach ($vias as $v)
                                    <option value="{{ $v->COD_VIA }}" @selected(old("items.$i.via") === $v->COD_VIA)>{{ $v->DES_VIA }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="1" max="72" name="items[{{ $i }}][frecuencia]" value="{{ old("items.$i.frecuencia") }}" placeholder="8"
                                   class="col-span-2 border border-slate-300 rounded-lg px-2 py-2 text-sm">
                            <input type="number" min="1" max="365" name="items[{{ $i }}][duracion]" value="{{ old("items.$i.duracion") }}" placeholder="5"
                                   class="col-span-2 border border-slate-300 rounded-lg px-2 py-2 text-sm">
                        </div>
                    @endfor
                    <input name="obs_receta" maxlength="200" value="{{ old('obs_receta') }}" placeholder="Indicaciones generales de la receta"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm mt-1">

                    @if (session('alerta_alergia') || $errors->has('justificacion'))
                        <div class="mt-3 rounded-xl border border-red-300 bg-red-50 p-3">
                            <p class="text-sm font-bold text-red-800"><i class="fas fa-exclamation-triangle"></i> Alerta de alergia (RN42)</p>
                            <ul class="text-sm text-red-800 list-disc ml-5">
                                @foreach ((array) session('alerta_alergia') as $al)
                                    <li>{{ $al }}</li>
                                @endforeach
                            </ul>
                            <label class="block text-sm font-semibold mt-2 mb-1" for="justificacion">Justificación para prescribir de todos modos</label>
                            <input id="justificacion" name="justificacion" maxlength="200" value="{{ old('justificacion') }}"
                                   class="w-full border border-red-300 rounded-lg px-3 py-2">
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-vials text-emerald-600"></i> Exámenes (RF019, opcional)</h2>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        @foreach ($examenes as $ex)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="examenes[]" value="{{ $ex->COD_SRV }}" @checked(in_array($ex->COD_SRV, old('examenes', []), true))>
                                {{ $ex->NOM_SRV }} <span class="text-xs text-slate-400">({{ $ex->DES_CLA }})</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <input name="indicaciones" maxlength="200" value="{{ old('indicaciones') }}" placeholder="Indicaciones clínicas de la orden"
                               class="col-span-2 border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <select name="prioridad" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="Normal" @selected(old('prioridad') !== 'Urgente')>Normal</option>
                            <option value="Urgente" @selected(old('prioridad') === 'Urgente')>Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-share-from-square text-emerald-600"></i> Derivación o interconsulta (RF023, opcional)</h2>
                    <div class="grid grid-cols-3 gap-3 mb-2">
                        <select name="deriv_esp" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">Especialidad destino…</option>
                            @foreach ($especialidades as $e)
                                <option value="{{ $e->COD_ESP }}" @selected(old('deriv_esp') === $e->COD_ESP)>{{ $e->DES_ESP }}</option>
                            @endforeach
                        </select>
                        <select name="deriv_tipo" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option>Interconsulta</option><option @selected(old('deriv_tipo') === 'Derivación')>Derivación</option>
                        </select>
                        <select name="deriv_prioridad" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            <option>Normal</option><option @selected(old('deriv_prioridad') === 'Urgente')>Urgente</option>
                        </select>
                    </div>
                    <input name="deriv_motivo" maxlength="300" value="{{ old('deriv_motivo') }}" placeholder="Motivo y pregunta clínica para el especialista"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-lg">
                        <i class="fas fa-check"></i> Guardar y marcar como atendida
                    </button>
                    <a href="{{ route('medico.dashboard') }}" class="bg-slate-100 text-slate-700 font-semibold px-5 py-2.5 rounded-lg">Volver a la agenda</a>
                </div>
            </form>
        </section>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 self-start">
            <a href="{{ route('medico.historia', $cita->COD_PAC) }}" class="block text-center bg-emerald-50 text-emerald-800 font-semibold text-sm rounded-lg px-3 py-2 mb-2">
                <i class="fas fa-folder-open"></i> Abrir historia clínica completa
            </a>
            <a href="{{ route('medico.historia.consentimiento', $cita->COD_PAC) }}" class="block text-center bg-slate-100 text-slate-700 font-semibold text-sm rounded-lg px-3 py-2 mb-4">
                <i class="fas fa-file-signature"></i> Consentimiento informado
            </a>
            <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-heart-pulse text-emerald-600"></i> Últimos signos vitales</h2>
            @if ($signo)
                <p class="text-sm mb-3">PA {{ $signo->SIS_SIV }}/{{ $signo->DIA_SIV }} · Pulso {{ $signo->PUL_SIV }} · T° {{ $signo->TEM_SIV }} °C
                    <span class="block text-xs text-slate-500">{{ \Carbon\Carbon::parse($signo->FEC_SIV)->format('d/m/Y H:i') }}</span></p>
            @else
                <p class="text-xs text-slate-500 mb-3">Sin registros.</p>
            @endif
            @if ($antecedentes->isNotEmpty())
                <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-book-medical text-emerald-600"></i> Antecedentes</h2>
                @foreach ($antecedentes as $a)
                    <p class="text-xs mb-1"><strong>{{ $a->TIP_ANT === 'Quirurgico' ? 'Quirúrgico' : $a->TIP_ANT }}:</strong> {{ $a->DES_ANT }}</p>
                @endforeach
                <hr class="my-3">
            @endif
            <h2 class="font-bold text-red-700 mb-2"><i class="fas fa-allergies"></i> Alergias</h2>
            @forelse ($alergias as $a)
                <p class="text-sm"><strong>{{ $a->DES_ALE }}</strong> <span class="text-xs text-red-600">({{ $a->SEV_ALE }})</span></p>
            @empty
                <p class="text-xs text-slate-500 mb-2">Sin alergias registradas.</p>
            @endforelse
            <hr class="my-3">
            <h2 class="font-bold text-slate-800 mb-2"><i class="fas fa-file-medical text-emerald-600"></i> Historia clínica</h2>
            @if ($historia)
                <p class="text-xs text-slate-500 mb-3">{{ $historia->NRO_HIS }} · abierta el {{ \Carbon\Carbon::parse($historia->FECAPE_HIS)->format('d/m/Y') }}</p>
                <h3 class="text-sm font-semibold mt-2 mb-1">Consultas anteriores</h3>
                @forelse ($anteriores as $a)
                    <div class="text-xs border-b border-slate-100 py-2">
                        <strong>{{ \Carbon\Carbon::parse($a->FEC_CNS)->format('d/m/Y') }}</strong> · {{ $a->TIT_EMP }} {{ $a->NOM_EMP }} {{ $a->APE_EMP }}<br>
                        {{ $a->DIA_CNS }}<br><span class="text-slate-500">{{ $a->PLA_CNS }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">Sin consultas anteriores.</p>
                @endforelse
                <h3 class="text-sm font-semibold mt-4 mb-1">Notas de evolución</h3>
                @forelse ($notas as $n)
                    <div class="text-xs border-b border-slate-100 py-2">
                        <strong>{{ \Carbon\Carbon::parse($n->FEC_NEV)->format('d/m/Y H:i') }}</strong><br>{{ $n->TXT_NEV }}
                    </div>
                @empty
                    <p class="text-xs text-slate-500">Sin notas.</p>
                @endforelse
            @else
                <p class="text-sm text-slate-500">El paciente aún no tiene historia clínica. Se abrirá al guardar esta atención.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// RF022: buscador del catálogo CIE-10
(function () {
    const input = document.getElementById('cie'), lista = document.getElementById('cie-lista');
    const desc = document.getElementById('cie-desc'), dia = document.getElementById('diagnostico');
    let mapa = {}, t = null;
    function buscar() {
        fetch(@json(route('medico.cie10')) + '?q=' + encodeURIComponent(input.value), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(filas => {
                lista.innerHTML = ''; mapa = {};
                filas.forEach(f => { mapa[f.COD_CIE] = f.DES_CIE; const o = document.createElement('option'); o.value = f.COD_CIE; o.label = f.COD_CIE + ' · ' + f.DES_CIE; o.textContent = f.DES_CIE; lista.appendChild(o); });
                elegir();
            });
    }
    function elegir() {
        const v = input.value.trim().toUpperCase();
        if (mapa[v]) { input.value = v; desc.textContent = v + ' · ' + mapa[v]; if (!dia.value) dia.value = mapa[v]; }
    }
    input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(buscar, 200); });
    input.addEventListener('change', elegir);
    buscar();
})();
</script>
@endpush
