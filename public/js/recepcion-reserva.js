(() => {
    const form = document.getElementById('form-reserva');
    if (!form) return;
    const busqueda = document.getElementById('buscar-paciente-reserva');
    const codigo = document.getElementById('paciente-codigo-reserva');
    const resultados = document.getElementById('pacientes-sugeridos');
    const especialidades = document.getElementById('especialidad-reserva');
    const estado = document.getElementById('estado-especialidades');
    let temporizador, peticion, seleccion;
    function limpiar() {
        codigo.value = '';
        especialidades.replaceChildren(new Option('Selecciona primero un paciente', ''));
        estado.textContent = 'La consulta general es el primer paso. Las especialidades se habilitan con una derivación.';
    }
    busqueda.addEventListener('input', () => {
        clearTimeout(temporizador);
        if (peticion) peticion.abort();
        if (seleccion) seleccion.abort();
        limpiar(); resultados.replaceChildren(); resultados.hidden = true;
        const q = busqueda.value.trim();
        if (q.length < 2) return;
        temporizador = setTimeout(async () => {
            peticion = new AbortController();
            try {
                const respuesta = await fetch(form.dataset.busqueda + '?q=' + encodeURIComponent(q), { signal: peticion.signal, headers: { Accept: 'application/json' } });
                if (!respuesta.ok) throw new Error();
                const lista = await respuesta.json();
                resultados.replaceChildren();
                if (!lista.length) { const mensaje = document.createElement('p'); mensaje.textContent = 'No se encontraron pacientes.'; resultados.append(mensaje); }
                for (const p of lista) {
                    const boton = document.createElement('button');
                    boton.type = 'button'; boton.setAttribute('role', 'option');
                    boton.style.cssText = 'display:block;text-align:left;width:100%;padding:10px;border:0;background:white;cursor:pointer';
                    boton.textContent = `${p.NOM_PAC} ${p.APE_PAC} · CI ${p.CI_PAC} · ${p.COD_PAC}`;
                    boton.addEventListener('click', async () => {
                        busqueda.value = boton.textContent; codigo.value = p.COD_PAC; resultados.hidden = true;
                        estado.textContent = 'Consultando derivaciones…'; especialidades.replaceChildren(new Option('Cargando…', ''));
                        if (seleccion) seleccion.abort(); seleccion = new AbortController();
                        try {
                            const r = await fetch(form.dataset.especialidades.replace('__PACIENTE__', encodeURIComponent(p.COD_PAC)), { signal: seleccion.signal, headers: { Accept: 'application/json' } });
                            if (!r.ok) throw new Error();
                            const opciones = await r.json(); especialidades.replaceChildren(new Option('Seleccionar especialidad', ''));
                            opciones.forEach(o => especialidades.add(new Option(`${o.nombre} · Bs ${Number(o.precio).toFixed(2)}`, o.codigo)));
                            estado.textContent = opciones.length ? 'Se muestran las especialidades autorizadas para este paciente. Precios especializados de prueba.' : 'No hay servicios disponibles; contacta a administración.';
                        } catch (e) { if (e.name !== 'AbortError') { limpiar(); estado.textContent = 'No se pudieron consultar las especialidades. Vuelve a seleccionar al paciente.'; } }
                    });
                    resultados.append(boton);
                }
                resultados.hidden = false;
            } catch (e) { if (e.name !== 'AbortError') estado.textContent = 'No se pudo buscar. Intenta nuevamente.'; }
        }, 250);
    });
    form.addEventListener('submit', e => { if (!codigo.value) { e.preventDefault(); busqueda.setCustomValidity('Selecciona un paciente de la lista.'); busqueda.reportValidity(); } });
    busqueda.addEventListener('input', () => busqueda.setCustomValidity(''));
})();
