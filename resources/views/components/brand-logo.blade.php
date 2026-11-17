@props(['width' => 170])
<a href="{{ auth()->check() ? route('panel') : route('login') }}" aria-label="Clínica CIES · Inicio" style="display:inline-flex;align-items:center;justify-content:center;max-width:100%;background:#fff;border-radius:6px;padding:2px 5px;flex-shrink:0;line-height:1">
    <img src="{{ asset('images/cies-logo.png') }}" alt="Clínica CIES · Salud sexual, salud reproductiva y salud integral" width="{{ $width }}" height="{{ round($width * 318 / 738) }}" style="display:block;width:{{ $width }}px;max-width:100%;height:auto;object-fit:contain">
</a>
