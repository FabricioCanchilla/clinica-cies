@extends('layouts.admin')

@section('titulo', 'Orden de pago')

@push('head')
<style>
    .op { background: #fff; border: 1px solid var(--borde); border-radius: 14px; padding: 26px; max-width: 720px; }
    .op table { width: 100%; border-collapse: collapse; }
    .op-cab { border-bottom: 3px solid var(--naranja); margin-bottom: 14px; }
    .op-cab td { padding-bottom: 10px; border: 0; }
    .op-marca { font-size: 19px; font-weight: 800; color: var(--cies-dark); }
    .op-sub { font-size: 12px; color: var(--suave); }
    .op-titulo { font-size: 16px; font-weight: 800; letter-spacing: .05em; }
    .op-nro { font-size: 14px; color: var(--naranja); font-weight: 700; }
    .op-datos td { padding: 5px 4px; border: 0; font-size: 14px; }
    .op-datos td.k { color: var(--suave); width: 120px; }
    .op-detalle { margin-top: 14px; }
    .op-detalle .menos td { color: var(--suave); }
    .op-detalle tfoot td { font-weight: 800; font-size: 16px; background: var(--cies-bg); color: var(--cies-dark); border-top: 2px solid var(--cies); padding: 12px; }
    .der { text-align: right; }
    .op-estado { margin: 14px 0; padding: 10px 14px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 14px; }
    .op-estado.pagada { background: var(--cies-bg); border-color: var(--cies-borde); color: #2d4c39; }
    .op-nota { font-size: 12px; color: var(--suave); line-height: 1.6; }
    @media print { aside, header, .no-imprimir { display: none !important; } .contenido { margin-left: 0 !important; } .op { border: 0; } }
</style>
@endpush

@section('contenido')
    <div class="acciones no-imprimir" style="margin-bottom: 16px;">
        <a href="{{ url()->previous() }}" class="btn"><i class="fas fa-arrow-left"></i> Volver</a>
        <a href="{{ route('ordenes.pago.pdf', $o->COD_CIT) }}" target="_blank" class="btn acento"><i class="fas fa-file-pdf"></i> PDF</a>
        <button type="button" class="btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
        @if (auth()->user()->COD_ROL === 'ROL005' && $o->COBRABLE)
            <a href="{{ route('caja.cobrar', $o->COD_CIT) }}" class="btn principal"><i class="fas fa-cash-register"></i> Cobrar Bs {{ number_format($o->SALDO, 2) }}</a>
        @endif
    </div>
    @include('ordenes._documento')
@endsection
