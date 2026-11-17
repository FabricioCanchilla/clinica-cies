<div class="day-heading"><h2>Citas del {{ $dia->copy()->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</h2><span class="day-count">{{ $citasDia->count() }} {{ $citasDia->count()===1?'paciente citado':'pacientes citados' }}</span></div>
<div class="appointments">
@forelse($citasDia as $c)
    <article class="appointment">
        <div class="appointment-top"><div class="appointment-time">{{ substr($c->HOR_CIT,0,5) }}</div><div><h3>{{ $c->NOM_PAC }} {{ $c->APE_PAC }}</h3><span class="muted">CI {{ $c->CI_PAC }} · {{ $c->COD_PAC }}</span><br><span class="badge {{ \Illuminate\Support\Str::slug($c->estado) }}">{{ $c->estado }}</span></div></div>
        <p><strong>Motivo:</strong> {{ $c->MOT_CIT }}</p><p>{{ $c->NOM_SRV }} · {{ $c->NOM_CON }}</p><p class="muted">{{ substr($c->HOR_CIT,0,5) }}–{{ \Carbon\Carbon::parse($c->FEC_CIT.' '.$c->HOR_CIT)->addMinutes($c->DUR_CIT)->format('H:i') }} · {{ $c->DUR_CIT }} minutos · Tel. {{ $c->TEL_PAC }}</p>
        @foreach($alergias->get($c->COD_PAC,collect()) as $a)<div class="danger">Alergia grave: {{ $a->DES_ALE }}</div>@endforeach
        @foreach($notasAdministrativas->get($c->COD_PAC,collect()) as $h)<p class="muted">{{ $h->NOTADM_HIS }}</p>@endforeach
        @if(!($pagos->get($c->COD_CIT)?->liquidado ?? false) && $c->PRE_SRV > ($pagos->get($c->COD_CIT)?->total ?? 0))<p class="muted">Saldo de consulta: Bs {{ number_format($c->PRE_SRV-($pagos->get($c->COD_CIT)?->total ?? 0),2) }}</p>@endif
        <div class="actions">
        @if(!in_array($c->EST_CIT,['Pendiente de pago','Cancelada','Expirada']))
            <a class="btn small" href="{{ route('medico.historias.ver',$c->COD_PAC) }}">Ver información del paciente</a>
            <a class="btn small secondary" href="{{ route('medico.citas.ver',$c->COD_CIT) }}">{{ $c->EST_CIT==='Atendida'?'Ver consulta':'Abrir consulta' }}</a>
        @endif
        @if($c->EST_CIT==='Confirmada' && $c->FEC_CIT===today()->toDateString())
            @foreach(['En espera'=>'Registrar llegada','En consulta'=>'Iniciar consulta','No asistio'=>'No asistió'] as $estado=>$etiqueta)
                @if(($estado==='En espera' && $c->estado==='Confirmada') || ($estado==='En consulta' && $c->estado!=='En consulta') || ($estado==='No asistio' && $c->estado!=='En consulta'))
                    <form method="post" action="{{ route('medico.citas.estado',$c->COD_CIT) }}">@csrf<input type="hidden" name="estado" value="{{ $estado }}"><button class="secondary">{{ $etiqueta }}</button></form>
                @endif
            @endforeach
        @endif
        </div>
    </article>
@empty
    <div class="empty-day"><strong>No hay citas para este día.</strong>Selecciona otra fecha del calendario o cambia los filtros.</div>
@endforelse
</div>
