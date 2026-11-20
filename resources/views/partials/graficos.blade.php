{{-- Gráficos con Chart.js y la paleta institucional CIES. Se incluye una sola vez por página. --}}
@once
@push('head')
<style>
    .graficos { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 18px; margin-bottom: 26px; }
    .graficos .card h2 { font-size: 15px; margin-bottom: 4px; }
    .graficos .card .nota { color: var(--suave); font-size: 12px; margin-bottom: 12px; }
    .lienzo { position: relative; height: 290px; }
    .lienzo.alto { height: 380px; }
    .lienzo.bajo { height: 230px; }
    .ancho-total { grid-column: 1 / -1; }
    .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .kpi { background: #fff; border: 1px solid var(--borde); border-radius: 14px; padding: 16px 18px; border-top: 4px solid var(--cies); }
    .kpi:nth-child(even) { border-top-color: var(--naranja); }
    .kpi .v { font-size: 26px; font-weight: 800; color: var(--titulo); line-height: 1.15; }
    .kpi .t { font-size: 13px; color: var(--suave); margin-top: 2px; }
    .kpi .d { font-size: 12px; margin-top: 6px; font-weight: 600; }
    .kpi .d.sube { color: #2d7a4a; } .kpi .d.baja { color: #b91c1c; }
    .kpi i.ico { float: right; color: var(--cies-borde); font-size: 22px; }
    .filtro-reporte { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; background: #fff; border: 1px solid var(--borde);
                      border-radius: 14px; padding: 14px 16px; margin-bottom: 22px; }
    .filtro-reporte label { display: block; font-size: 11px; font-weight: 700; color: var(--suave); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
    .filtro-reporte select, .filtro-reporte input { padding: 8px 10px; border: 1px solid var(--borde-input); border-radius: 8px; font: inherit; font-size: 14px; background: #fff; }
    .filtro-reporte .grupo-rango { display: none; gap: 10px; }
    .filtro-reporte.rango .grupo-rango { display: flex; }
    .filtro-reporte.rango .grupo-fecha { display: none; }
    .rango-actual { font-size: 13px; color: var(--cies-dark); font-weight: 600; margin: -10px 0 18px; }
    .podio td:first-child { font-weight: 800; color: var(--naranja); width: 34px; }
    .barra-mini { height: 8px; border-radius: 99px; background: var(--cies-bg); overflow: hidden; min-width: 80px; }
    .barra-mini span { display: block; height: 100%; background: var(--cies); border-radius: 99px; }
    @media print {
        aside, header, .filtro-reporte, .no-imprimir { display: none !important; }
        .contenido { margin-left: 0 !important; }
        main { padding: 0 !important; max-width: none !important; }
        .graficos { grid-template-columns: 1fr 1fr; }
        .card { break-inside: avoid; box-shadow: none; }
        body { background: #fff; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    window.CIES = window.CIES || {};
    CIES.colores = ['#4c7d5f', '#cc783c', '#3b82f6', '#a855f7', '#e11d48', '#0d9488', '#eab308', '#64748b',
                    '#f97316', '#14b8a6', '#8b5cf6', '#84cc16', '#ef4444', '#06b6d4'];
    CIES.bs = v => 'Bs ' + Number(v).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (window.Chart) {
        Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";
        Chart.defaults.color = '#475569';
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.maintainAspectRatio = false;
    }

    /** series: { 'Nombre': [valores...] } */
    CIES.barras = function (id, etiquetas, series, op = {}) {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        const nombres = Object.keys(series);
        new Chart(el, {
            type: 'bar',
            data: {
                labels: etiquetas,
                datasets: nombres.map((n, i) => ({
                    label: n, data: series[n], borderRadius: 6, maxBarThickness: 46,
                    backgroundColor: op.colores ? (nombres.length > 1 ? op.colores[i % op.colores.length] : op.colores) : (nombres.length === 1 && op.multicolor ? etiquetas.map((_, j) => CIES.colores[j % 14]) : CIES.colores[i % 14]),
                })),
            },
            options: {
                indexAxis: op.horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: nombres.length > 1 },
                    tooltip: { callbacks: { label: c => (nombres.length > 1 ? c.dataset.label + ': ' : '') + (op.moneda ? CIES.bs(c.raw) : c.raw + (op.sufijo || '')) } },
                },
                scales: {
                    x: { stacked: !!op.apilado, grid: { display: !!op.horizontal }, ticks: op.horizontal && op.moneda ? { callback: v => CIES.bs(v) } : {} },
                    y: { stacked: !!op.apilado, beginAtZero: true, grid: { display: !op.horizontal }, ticks: !op.horizontal && op.moneda ? { callback: v => 'Bs ' + v } : { precision: 0 } },
                },
            },
        });
    };

    CIES.linea = function (id, etiquetas, series, op = {}) {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        const nombres = Object.keys(series);
        new Chart(el, {
            type: 'line',
            data: {
                labels: etiquetas,
                datasets: nombres.map((n, i) => ({
                    label: n, data: series[n], tension: .3, fill: i === 0, pointRadius: etiquetas.length > 40 ? 0 : 3,
                    borderColor: CIES.colores[i % 14], backgroundColor: i === 0 ? 'rgba(76,125,95,.12)' : CIES.colores[i % 14],
                    borderWidth: 2, borderDash: i > 0 ? [5, 4] : [],
                })),
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: nombres.length > 1 } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 16 } } },
            },
        });
    };

    CIES.dona = function (id, etiquetas, valores, op = {}) {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels: etiquetas, datasets: [{ data: valores, backgroundColor: op.colores || CIES.colores, borderWidth: 2, borderColor: '#fff' }] },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { position: 'right' },
                    tooltip: { callbacks: { label: c => {
                        const t = c.dataset.data.reduce((a, b) => a + b, 0) || 1;
                        return ' ' + c.label + ': ' + c.raw + ' (' + Math.round(c.raw * 1000 / t) / 10 + ' %)';
                    } } },
                },
            },
        });
    };
</script>
@endpush
@endonce
