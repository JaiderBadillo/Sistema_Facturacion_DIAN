'use strict';

document.querySelectorAll('input[data-filtro]').forEach(input => {
    const rows = document.querySelectorAll('#' + input.dataset.filtro + ' tbody tr');
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        rows.forEach(row => { row.hidden = q !== '' && !row.textContent.toLowerCase().includes(q); });
    });
});

// Filtros de la tabla de facturas: texto, emisor y rango de fechas. El resumen suma solo lo visible.
const tablaFacturas = document.querySelector('#tabla-facturas');
if (tablaFacturas) {
    const rows = [...tablaFacturas.querySelectorAll('tbody tr')];
    const $ = id => document.querySelector('#' + id);
    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 });
    function aplicar() {
        const q = $('f-texto').value.trim().toLowerCase(), nit = $('f-emisor').value, desde = $('f-desde').value, hasta = $('f-hasta').value;
        let visibles = 0, suma = 0;
        rows.forEach(r => {
            const d = r.dataset;
            const ok = (!q || r.textContent.toLowerCase().includes(q)) && (!nit || d.nit === nit)
                && (!desde || d.fecha >= desde) && (!hasta || d.fecha <= hasta);
            r.hidden = !ok;
            if (ok) { visibles++; suma += Number(d.total); }
        });
        $('f-resumen').textContent = 'Mostrando ' + visibles + ' de ' + rows.length + ' facturas · Total ' + money.format(suma)
            + (visibles === 0 ? ' · Ninguna coincide con los filtros.' : '');
    }
    $('filtros-facturas').addEventListener('input', aplicar);
    $('f-limpiar').addEventListener('click', () => setTimeout(aplicar));
    aplicar();
}
