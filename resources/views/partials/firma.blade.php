{{-- RN15: firma manuscrita en pantalla (ratón, dedo o lápiz). Uso:
     @include('partials.firma', ['form' => 'id-del-form', 'campo' => 'firma', 'quien' => 'el paciente']) --}}
@php $idLienzo = 'lienzo-' . $form; @endphp
<label>Firma de {{ $quien }} (con el mouse o el dedo)</label>
<canvas id="{{ $idLienzo }}" width="600" height="200"
        style="width: 100%; max-width: 600px; height: 200px; border: 1px dashed var(--borde-input); border-radius: 8px; background: #fff; touch-action: none; display: block;"></canvas>
<button type="button" class="btn sm" id="{{ $idLienzo }}-limpiar" style="margin: 8px 0 16px;"><i class="fas fa-eraser"></i> Borrar firma</button>
<input type="hidden" name="{{ $campo }}" id="{{ $idLienzo }}-valor">
<script>
    (function () {
        const c = document.getElementById(@json($idLienzo));
        const ctx = c.getContext('2d');
        ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
        let dibujando = false, trazos = 0;
        const punto = e => { const r = c.getBoundingClientRect(); return { x: (e.clientX - r.left) * c.width / r.width, y: (e.clientY - r.top) * c.height / r.height }; };
        c.addEventListener('pointerdown', e => { dibujando = true; const p = punto(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); c.setPointerCapture(e.pointerId); });
        c.addEventListener('pointermove', e => { if (!dibujando) return; const p = punto(e); ctx.lineTo(p.x, p.y); ctx.stroke(); trazos++; });
        ['pointerup', 'pointerleave', 'pointercancel'].forEach(t => c.addEventListener(t, () => { dibujando = false; }));
        document.getElementById(@json($idLienzo . '-limpiar')).addEventListener('click', () => { ctx.clearRect(0, 0, c.width, c.height); trazos = 0; });
        document.getElementById(@json($form)).addEventListener('submit', e => {
            if (trazos < 10) { e.preventDefault(); alert(@json('Falta la firma de ' . $quien . '.')); return; }
            document.getElementById(@json($idLienzo . '-valor')).value = c.toDataURL('image/png');
        });
    })();
</script>
