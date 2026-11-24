'use strict';

const form = document.querySelector('#emision');
const fields = form.elements;
const result = document.querySelector('#resultado');
const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 });
const serializer = new XMLSerializer();
const ns = 'http://uts.edu.co/facturacion';
const soap = 'http://schemas.xmlsoap.org/soap/envelope/';

const clients = JSON.parse(document.querySelector('#clientes-data').textContent);
let preferred = form.dataset.cliente;
function setClients() {
    const list = clients[fields.nit.value] || [];
    fields.receptor.replaceChildren(...list.map(c => new Option(c.nombre + ' · ' + c.identificacion, c.identificacion)));
    if (preferred && list.some(c => c.identificacion === preferred)) fields.receptor.value = preferred;
    preferred = '';
    syncCorreo();
    document.querySelector('#nuevo-cliente').href = '/index.php?vista=cliente&nit=' + encodeURIComponent(fields.nit.value);
}
function syncCorreo() {
    const client = (clients[fields.nit.value] || []).find(c => c.identificacion === fields.receptor.value);
    const box = document.querySelector('#enviar-correo');
    box.disabled = !client?.correo;
    box.checked = !!client?.correo;
    document.querySelector('#correo-cliente').textContent = client?.correo ? 'Se enviará a ' + client.correo : 'Este cliente no tiene correo registrado. Agrega uno nuevo con su correo para poder enviarle la factura.';
}
function setIssuer() {
    setClients();
    const issuer = fields.nit.selectedOptions[0].dataset;
    fields.consecutivo.min = issuer.min;
    fields.consecutivo.max = issuer.max;
    fields.consecutivo.value = issuer.next;
    document.querySelector('#rango').textContent = 'Prefijo ' + issuer.prefix + ' · Consecutivos del ' + issuer.min + ' al ' + issuer.max + '.';
}
function totalCents() {
    return Math.round(Number(fields.subtotal.value) * 100) + Math.round(Number(fields.iva.value) * 100);
}
function append(parent, name, value, namespace = null) {
    const node = parent.ownerDocument.createElementNS(namespace, name);
    if (value !== undefined) node.textContent = value;
    parent.appendChild(node);
    return node;
}
function invoiceRequest(number) {
    const invoice = document.implementation.createDocument(null, 'Factura');
    const root = invoice.documentElement;
    append(root, 'Numero', number);
    append(root, 'Fecha', fields.fecha.value);
    const issuer = append(root, 'Emisor');
    append(issuer, 'NIT', fields.nit.value);
    append(issuer, 'RazonSocial', fields.nit.selectedOptions[0].dataset.name);
    const client = clients[fields.nit.value].find(c => c.identificacion === fields.receptor.value);
    const receiver = append(root, 'Receptor');
    append(receiver, 'Identificacion', client.identificacion);
    append(receiver, 'Nombre', client.nombre);
    const sendMail = document.querySelector('#enviar-correo').checked && client.correo;
    if (sendMail) append(receiver, 'Correo', client.correo);
    const totals = append(root, 'Totales');
    append(totals, 'Subtotal', Number(fields.subtotal.value).toFixed(2));
    append(totals, 'IVA', Number(fields.iva.value).toFixed(2));
    append(totals, 'Total', (totalCents() / 100).toFixed(2));
    if (sendMail) append(root, 'EnvioCorreo', 'true');

    const envelope = document.implementation.createDocument(soap, 'soap:Envelope');
    const body = append(envelope.documentElement, 'soap:Body', undefined, soap);
    const request = append(body, 'fac:EnviarFacturaRequest', undefined, ns);
    append(request, 'fac:nitEmisor', fields.nit.value, ns);
    append(request, 'fac:facturaXml', serializer.serializeToString(invoice), ns);
    return serializer.serializeToString(envelope);
}
fields.nit.addEventListener('change', setIssuer);
fields.receptor.addEventListener('change', syncCorreo);
// IVA general de 19 % calculado en centavos; deja de autocalcularse si el usuario lo edita.
let ivaManual = false;
fields.iva.addEventListener('input', () => { ivaManual = fields.iva.value !== ''; });
fields.subtotal.addEventListener('input', () => {
    if (!ivaManual) fields.iva.value = (Math.round(Number(fields.subtotal.value) * 19) / 100).toFixed(2);
});
form.addEventListener('input', () => {
    result.textContent = '';
    document.querySelector('#importe').textContent = money.format(totalCents() / 100);
});
form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!fields.receptor.value) {
        result.textContent = 'Selecciona un cliente o agrega uno nuevo.';
        fields.receptor.focus();
        return;
    }
    const button = form.querySelector('button');
    const number = fields.nit.selectedOptions[0].dataset.prefix + Number(fields.consecutivo.value);
    button.disabled = true;
    result.textContent = 'Validando ' + number + '…';
    try {
        const response = await fetch('/servicio.php', {
            method: 'POST',
            headers: { 'Content-Type': 'text/xml; charset=utf-8', 'SOAPAction': '"' + ns + '/EnviarFactura"' },
            body: invoiceRequest(number)
        });
        const xml = new DOMParser().parseFromString(await response.text(), 'application/xml');
        const value = name => xml.getElementsByTagNameNS('*', name)[0]?.textContent;
        if (!response.ok || value('estado') !== 'APROBADA') {
            result.textContent = value('mensaje') || value('faultstring') || 'El servicio no pudo validar la factura. Comprueba que SOAP esté activo.';
            return;
        }
        window.location.assign('/index.php?vista=detalle&numero=' + encodeURIComponent(number));
    } catch {
        result.textContent = 'Se perdió la conexión. Consulta el registro antes de volver a enviar.';
    } finally {
        button.disabled = false;
    }
});
setIssuer();
