{{-- Indicadores y gráficos de la pantalla principal de un rol. Variable: $panel = ['kpis' => [...], 'graficos' => [...]]
     (lo arma App\Services\IndicadoresRolService). Usa los ayudantes de Chart.js de partials.graficos. --}}
@include('partials.graficos')

@if (!empty($panel['kpis']))
    <div class="kpis">
        @foreach ($panel['kpis'] as $k)
            <div class="kpi">
                <i class="fas {{ $k['ico'] ?? 'fa-chart-simple' }} ico"></i>
                <div class="v">{{ $k['v'] }}</div>
                <div class="t">{{ $k['t'] }}</div>
                @if (!empty($k['d']))<div class="d" style="color: var(--suave);">{{ $k['d'] }}</div>@endif
            </div>
        @endforeach
    </div>
@endif

@if (!empty($panel['graficos']))
    <div class="graficos">
        @foreach ($panel['graficos'] as $g)
            @php $vacio = empty($g['etiquetas']) || (isset($g['valores']) && array_sum($g['valores']) == 0)
                || (isset($g['series']) && collect($g['series'])->flatten()->sum() == 0); @endphp
            <div class="card {{ !empty($g['ancho']) ? 'ancho-total' : '' }}">
                <h2>{{ $g['titulo'] }}</h2>
                <p class="nota">{{ $g['nota'] ?? '' }}</p>
                @if ($vacio)
                    <div class="vacio" style="height: 230px; display: flex; flex-direction: column; justify-content: center;">
                        <i class="fas fa-chart-simple"></i> Todavía no hay datos para este gráfico.
                    </div>
                @else
                    <div class="lienzo {{ !empty($g['alto']) ? 'alto' : '' }}"><canvas id="{{ $g['id'] }}" role="img" aria-label="{{ $g['titulo'] }}"></canvas></div>
                @endif
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
    (function () {
        const graficos = @json($panel['graficos']);
        const dibujar = () => graficos.forEach(g => {
            const el = document.getElementById(g.id);
            if (!el || el.dataset.dibujado) return;
            el.dataset.dibujado = '1';
            const op = g.op || {};
            if (g.tipo === 'dona') {
                CIES.dona(g.id, g.etiquetas, g.valores, op.colores ? { colores: op.colores } : {});
            } else if (g.tipo === 'linea') {
                CIES.linea(g.id, g.etiquetas, g.series, op);
            } else {
                CIES.barras(g.id, g.etiquetas, g.series, op);
            }
        });
        if (window.Chart) dibujar(); else window.addEventListener('load', dibujar);
    })();
    </script>
    @endpush
@endif
