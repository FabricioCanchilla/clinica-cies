<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atender cita - Clínica CIES</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 text-slate-800">
    <header class="bg-gradient-to-r from-emerald-700 to-emerald-500 text-white px-8 py-5 text-xl font-bold">
        Clínica CIES · Atención médica
    </header>

    <main class="max-w-5xl mx-auto p-6 grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <h1 class="text-2xl font-bold text-emerald-800 mb-1">Cita {{ $cita->COD_CIT }}</h1>
            <p class="text-sm text-slate-600 mb-4">
                {{ \Carbon\Carbon::parse($cita->FEC_CIT)->format('d/m/Y') }} · {{ substr($cita->HOR_CIT, 0, 5) }} · {{ $cita->NOM_SRV }}<br>
                <strong>Paciente:</strong> {{ $cita->NOM_PAC }} {{ $cita->APE_PAC }} (CI {{ $cita->CI_PAC }}, {{ \Carbon\Carbon::parse($cita->FECNAC_PAC)->age }} años, {{ $cita->SEX_PAC === 'F' ? 'femenino' : 'masculino' }})<br>
                <strong>Motivo:</strong> {{ $cita->MOT_CIT }}
            </p>

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('medico.atender.guardar', $cita->COD_CIT) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1" for="diagnostico">Diagnóstico</label>
                    <input id="diagnostico" name="diagnostico" maxlength="200" required value="{{ old('diagnostico') }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="Diagnóstico resumido">
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
                <div class="flex gap-3">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-lg">
                        <i class="fas fa-check"></i> Guardar y marcar como atendida
                    </button>
                    <a href="{{ route('medico.dashboard') }}" class="bg-slate-100 text-slate-700 font-semibold px-5 py-2.5 rounded-lg">Volver a la agenda</a>
                </div>
            </form>
        </section>

        <aside class="bg-white rounded-2xl border border-slate-200 p-6">
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
        </aside>
    </main>
</body>
</html>
