<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$repo = new Repositorio($config['data_dir']);
$emisores = $repo->listarContribuyentes();
function h(mixed $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pesos(float $importe): string {
    return '$' . number_format($importe, 2, ',', '.');
}
function fechaCorta(string $iso): string {
    $meses = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
    return $fecha ? $fecha->format('d') . ' ' . $meses[(int) $fecha->format('n')] . ' ' . $fecha->format('Y') : $iso;
}
// Siguiente consecutivo del emisor: el mayor número ya registrado dentro de su rango + 1, o el inicio del rango.
function siguienteConsecutivo(Repositorio $repo, string $nit): int {
    $r = $repo->obtenerRangos($nit);
    $ultimo = $r['desde'] - 1;
    foreach ($repo->listarFacturas() as $f) {
        if ($f['emisor_nit'] === $nit && preg_match('/\A' . preg_quote($r['prefijo'], '/') . '(\d+)\z/', $f['numero'], $m)) {
            $n = (int) $m[1];
            if ($n >= $r['desde'] && $n <= $r['hasta']) $ultimo = max($ultimo, $n);
        }
    }
    return min($ultimo + 1, $r['hasta']);
}
$vista = $_GET['vista'] ?? 'registro';
$factura = null;
if ($vista === 'detalle') {
    $numero = $_GET['numero'] ?? '';
    $factura = is_string($numero) ? $repo->obtenerFactura($numero) : null;
    if (!$factura) { http_response_code(404); }
} elseif (!in_array($vista, ['nueva', 'cliente', 'clientes', 'emisor'], true)) {
    $vista = 'registro';
    $facturas = $repo->listarFacturas();
    $total = array_sum(array_column($facturas, 'total'));
}
$errorCliente = null;
$errorEmisor = null;
if ($vista === 'emisor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $campo = fn(string $k): string => trim((string) ($_POST[$k] ?? ''));
    try {
        $nuevoEmisor = $repo->agregarEmisor($campo('nit'), $campo('razon_social'), strtoupper($campo('prefijo')), $campo('desde'), $campo('hasta'), $campo('resolucion'), $campo('vigencia'));
        header('Location: /index.php?vista=cliente&nit=' . rawurlencode($nuevoEmisor['nit']) . '&creado=1');
        exit;
    } catch (DomainException $e) {
        $errorEmisor = $e->getMessage();
    }
}
if ($vista === 'clientes') {
    $nitCliente = (string) ($_GET['nit'] ?? '');
    $emisorCliente = $repo->obtenerContribuyente($nitCliente);
    if (!$emisorCliente) { http_response_code(404); }
}
if ($vista === 'cliente') {
    $nitCliente = (string) ($_GET['nit'] ?? '');
    $emisorCliente = $repo->obtenerContribuyente($nitCliente);
    if (!$emisorCliente) {
        http_response_code(404);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $nuevo = $repo->agregarCliente($nitCliente, trim((string) ($_POST['identificacion'] ?? '')), trim((string) ($_POST['nombre'] ?? '')), trim((string) ($_POST['correo'] ?? '')));
            header('Location: /index.php?vista=nueva&nit=' . rawurlencode($nitCliente) . '&cliente=' . rawurlencode($nuevo['identificacion']));
            exit;
        } catch (DomainException $e) {
            $errorCliente = $e->getMessage();
        }
    }
}
$titulo = match ($vista) {
    'emisor' => 'Nuevo emisor',
    'clientes' => 'Clientes',
    'cliente' => 'Nuevo cliente',
    'nueva' => 'Nueva factura',
    'detalle' => $factura['numero'] ?? 'Factura no encontrada',
    default => 'Registro de facturación',
};
?>
<!doctype html>
<html lang="es-CO">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · Folio</title>
<link rel="icon" href="data:,"><link rel="stylesheet" href="/folio.css">
<?php if ($vista === 'nueva'): ?><script src="/emision.js" defer></script><?php endif; ?>
<?php if (in_array($vista, ['registro', 'clientes'], true)): ?><script src="/buscar.js" defer></script><?php endif; ?>
</head>
<body>
<header><div class="mast wrap">
    <a href="/index.php" class="brand" aria-label="Folio, inicio"><i class="logomark" aria-hidden="true"></i>Folio<span>.</span></a>
    <nav aria-label="Navegación principal"><a href="/index.php" <?= $vista === 'registro' ? 'aria-current="page"' : '' ?>>Registro</a><a href="/index.php#emisores">Emisores</a></nav>
    <a class="wsdl" href="/servicio.php?wsdl">Contrato WSDL <span aria-hidden="true">↗</span></a>
</div></header>
<main class="wrap">
<?php if ($vista === 'registro'): ?>
<div class="heading"><div><p class="kicker">Facturación electrónica</p><h1>Registro de facturación</h1></div>
    <a class="button" href="/index.php?vista=nueva"><span aria-hidden="true">+</span> Nueva factura</a></div>
<div class="summary" aria-label="Resumen del registro">
    <div class="metric amount"><span>Total facturado</span><strong><?= h(pesos($total)) ?> <small>COP</small></strong></div>
    <div class="metric"><span>Facturas aprobadas</span><strong><?= count($facturas) ?></strong></div>
    <div class="metric"><span>Emisores autorizados</span><strong><?= count($emisores) ?></strong></div>
</div>
<section><div class="section-head"><h2>Facturas registradas</h2><span class="note">Más recientes primero</span></div>
<?php if ($facturas): ?>
<form class="filters" id="filtros-facturas" onsubmit="return false">
<label>Buscar<input type="search" id="f-texto" placeholder="Número, receptor, emisor o NIT"></label>
<label>Emisor<select id="f-emisor"><option value="">Todos</option><?php foreach ($emisores as $e): ?><option value="<?= h($e['nit']) ?>"><?= h($e['razon_social']) ?></option><?php endforeach; ?></select></label>
<label>Desde<input type="date" id="f-desde"></label>
<label>Hasta<input type="date" id="f-hasta"></label>
<button class="button ghost" type="reset" id="f-limpiar">Limpiar</button>
</form>
<p class="note" id="f-resumen" role="status" aria-live="polite"></p>
<?php endif; ?>
<?php if ($facturas): ?>
<div class="table-scroll" tabindex="0" role="region" aria-label="Listado de facturas"><table id="tabla-facturas">
<thead><tr><th>Número</th><th>Fecha de emisión</th><th>Emisor / NIT</th><th>Receptor</th><th>Estado</th><th class="money">Total en COP</th></tr></thead>
<tbody><?php foreach ($facturas as $f): ?><tr data-nit="<?= h($f['emisor_nit']) ?>" data-fecha="<?= h($f['fecha']) ?>" data-total="<?= h($f['total']) ?>">
<td><a class="number" href="?vista=detalle&amp;numero=<?= rawurlencode($f['numero']) ?>"><?= h($f['numero']) ?></a></td>
<td class="nowrap"><?= h(fechaCorta($f['fecha'])) ?></td><td><?= h($f['emisor_nombre']) ?><small><?= h($f['emisor_nit']) ?></small></td>
<td><?= h($f['receptor'] ?: 'Sin especificar') ?></td><td><span class="status"><?= h(ucfirst(strtolower($f['estado']))) ?></span></td>
<td class="money"><?= h(pesos($f['total'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><p class="empty">Aún no hay facturas registradas. Selecciona «Nueva factura» o envía un documento desde el cliente SOAP.</p><?php endif; ?></section>
<section id="emisores"><div class="section-head"><h2>Emisores autorizados</h2>
<span class="tools"><input type="search" class="filter" data-filtro="tabla-emisores" placeholder="Buscar por nombre, NIT o prefijo" aria-label="Buscar emisor">
<a class="button" href="/index.php?vista=emisor"><span aria-hidden="true">+</span> Nuevo emisor</a></span></div>
<div class="table-scroll" tabindex="0" role="region" aria-label="Emisores autorizados"><table id="tabla-emisores">
<thead><tr><th>Prefijo</th><th>Emisor</th><th>NIT</th><th>Resolución</th><th>Numeración</th><th>Vigencia</th><th class="money">Clientes</th></tr></thead>
<tbody><?php foreach ($emisores as $emisor): $r = $repo->obtenerRangos($emisor['nit']); ?><tr>
<td><span class="prefix"><?= h($r['prefijo']) ?></span></td><td><strong><?= h($emisor['razon_social']) ?></strong></td><td class="nowrap"><?= h($emisor['nit']) ?></td>
<td class="nowrap"><?= h($r['resolucion']) ?></td><td class="nowrap"><?= h($r['desde']) ?> – <?= h($r['hasta']) ?></td><td class="nowrap"><?= h(fechaCorta($r['fecha_vigencia'])) ?></td>
<td class="money"><a href="/index.php?vista=clientes&amp;nit=<?= rawurlencode($emisor['nit']) ?>">Ver <?= count($repo->listarClientes($emisor['nit'])) ?></a></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
<a class="back" href="/index.php">← Volver al registro</a>
<div class="heading inner"><div><p class="kicker">Facturación electrónica</p><h1><?= h($titulo) ?></h1></div></div>
<?php if ($vista === 'nueva'): ?>
<form id="emision" data-cliente="<?= h($_GET['cliente'] ?? '') ?>"><noscript>Activa JavaScript para enviar desde el navegador o utiliza el cliente SOAP de consola.</noscript>
<div class="form-grid">
<fieldset><legend>Datos de emisión</legend>
<label>Emisor<select name="nit" required><?php foreach ($emisores as $emisor): $r = $repo->obtenerRangos($emisor['nit']); ?>
<option value="<?= h($emisor['nit']) ?>" <?= $emisor['nit'] === ($_GET['nit'] ?? '') ? 'selected' : '' ?> data-prefix="<?= h($r['prefijo']) ?>" data-min="<?= h($r['desde']) ?>" data-max="<?= h($r['hasta']) ?>" data-next="<?= h(siguienteConsecutivo($repo, $emisor['nit'])) ?>" data-name="<?= h($emisor['razon_social']) ?>"><?= h($emisor['razon_social']) ?></option>
<?php endforeach; ?></select></label>
<div class="pair"><label>Consecutivo<input type="number" name="consecutivo" required step="1" inputmode="numeric" aria-describedby="rango"></label><label>Fecha de emisión<input type="date" name="fecha" value="<?= h(date('Y-m-d')) ?>" required></label></div><small id="rango"></small>
</fieldset>
<fieldset><legend>Receptor e importes</legend>
<label>Cliente (receptor)<select name="receptor" required></select></label>
<small><a id="nuevo-cliente" href="/index.php?vista=cliente">+ Agregar un cliente nuevo a este emisor</a></small>
<label class="check"><input type="checkbox" id="enviar-correo"> Enviar la factura (PDF y XML) al correo del cliente</label>
<small id="correo-cliente"></small>
<div class="pair"><label>Subtotal (COP)<input type="number" name="subtotal" min="0" max="999999999999.99" step="0.01" required></label><label>IVA (COP) · 19 % automático, editable<input type="number" name="iva" min="0" max="999999999999.99" step="0.01" required></label></div>
<div class="calculation"><span>Total a registrar</span><output id="importe">$0,00</output></div>
</fieldset></div>
<p id="resultado" role="status" aria-live="polite"></p>
<div class="actions"><a class="back" href="/index.php">Cancelar</a><button class="button" type="submit">Enviar a validación</button></div></form>
<?php $clientesPorNit = []; foreach ($emisores as $e) { $clientesPorNit[$e['nit']] = $repo->listarClientes($e['nit']); } ?>
<script type="application/json" id="clientes-data"><?= json_encode($clientesPorNit, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php elseif ($vista === 'emisor'): ?>
<form method="post" action="/index.php?vista=emisor">
<div class="form-grid">
<fieldset><legend>Datos del emisor</legend>
<label>NIT (solo dígitos)<input name="nit" inputmode="numeric" maxlength="12" required value="<?= h($_POST['nit'] ?? '') ?>"></label>
<div class="pair"><label>Razón social<input name="razon_social" maxlength="160" required value="<?= h($_POST['razon_social'] ?? '') ?>"></label>
<label>Resolución de numeración<input name="resolucion" maxlength="30" required placeholder="RES-18760000005" value="<?= h($_POST['resolucion'] ?? '') ?>"></label></div>
</fieldset>
<fieldset><legend>Numeración autorizada</legend>
<div class="pair"><label>Prefijo (letras mayúsculas)<input name="prefijo" maxlength="6" required style="text-transform:uppercase" value="<?= h($_POST['prefijo'] ?? '') ?>"></label>
<label>Vigente hasta<input type="date" name="vigencia" min="<?= h(date('Y-m-d')) ?>" required value="<?= h($_POST['vigencia'] ?? '') ?>"></label></div>
<div class="pair"><label>Desde<input type="number" name="desde" min="1" step="1" required value="<?= h($_POST['desde'] ?? '') ?>"></label>
<label>Hasta<input type="number" name="hasta" min="2" step="1" required value="<?= h($_POST['hasta'] ?? '') ?>"></label></div>
</fieldset></div>
<?php if ($errorEmisor): ?><p role="alert"><?= h($errorEmisor) ?></p><?php endif; ?>
<div class="actions"><a class="back" href="/index.php#emisores">Cancelar</a><button class="button" type="submit">Guardar emisor</button></div></form>
<?php elseif ($vista === 'clientes'): ?>
<?php if (!$emisorCliente): ?><p class="empty">El emisor indicado no existe.</p><?php else: $clientes = $repo->listarClientes($nitCliente); ?>
<div class="section-head"><p class="note"><?= h($emisorCliente['razon_social']) ?> · NIT <?= h($nitCliente) ?> · <?= count($clientes) ?> clientes</p>
<span class="tools"><input type="search" class="filter" data-filtro="tabla-clientes" placeholder="Buscar por nombre o identificación" aria-label="Buscar cliente">
<a class="button" href="/index.php?vista=cliente&amp;nit=<?= rawurlencode($nitCliente) ?>"><span aria-hidden="true">+</span> Agregar cliente</a></span></div>
<div class="table-scroll" tabindex="0" role="region" aria-label="Clientes del emisor"><table id="tabla-clientes">
<thead><tr><th>Identificación</th><th>Nombre o razón social</th><th>Correo</th></tr></thead>
<tbody><?php foreach ($clientes as $c): ?><tr><td class="nowrap"><?= h($c['identificacion']) ?></td><td><?= h($c['nombre']) ?></td><td><?= h(($c['correo'] ?? '') ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php elseif ($vista === 'cliente'): ?>
<?php if (!$emisorCliente): ?><p class="empty">El emisor indicado no existe.</p><?php else: ?>
<?php if (isset($_GET['creado'])): ?><p class="note">Emisor creado. Agrega ahora su primer cliente para poder facturar.</p><?php endif; ?>
<form method="post" action="/index.php?vista=cliente&amp;nit=<?= rawurlencode($nitCliente) ?>">
<fieldset><legend>Cliente de <?= h($emisorCliente['razon_social']) ?></legend>
<label>Identificación (cédula o NIT)<input name="identificacion" maxlength="20" required value="<?= h($_POST['identificacion'] ?? '') ?>"></label>
<div class="pair"><label>Nombre o razón social<input name="nombre" maxlength="160" required value="<?= h($_POST['nombre'] ?? '') ?>"></label>
<label>Correo electrónico (para enviarle la factura)<input type="email" name="correo" maxlength="254" value="<?= h($_POST['correo'] ?? '') ?>"></label></div>
</fieldset>
<?php if ($errorCliente): ?><p role="alert"><?= h($errorCliente) ?></p><?php endif; ?>
<div class="actions"><a class="back" href="/index.php?vista=nueva&amp;nit=<?= rawurlencode($nitCliente) ?>">Cancelar</a><button class="button" type="submit">Guardar cliente</button></div></form>
<?php endif; ?>
<?php elseif ($factura): ?>
<?php $ec = $repo->obtenerEstadoCorreo($factura['numero']);
$textoCorreo = !($factura['enviar_correo'] ?? false) ? 'No solicitado' : match ($ec['estado'] ?? '') {
    'ENVIADO' => 'Enviado a ' . $ec['destino'] . ' (' . $ec['fecha'] . ')',
    'CONTINGENCIA' => 'En contingencia: se reintenta hasta el ' . $ec['plazo'] . ' (intento ' . $ec['intentos'] . '). Último error: ' . ($ec['detalle'] ?? ''),
    'VENCIDA' => 'No se pudo entregar dentro de las ' . EnvioCorreo::PLAZO_HORAS . ' h. Último error: ' . ($ec['detalle'] ?? ''),
    default => 'En cola de envío para ' . ($factura['receptor_correo'] ?? ''),
}; ?>
<div class="receipt"><div class="receipt-head"><span class="status">Aprobada</span><span><?= h(fechaCorta($factura['fecha'])) ?></span></div>
<dl><?php foreach (['Emisor' => $factura['emisor_nombre'], 'NIT del emisor' => $factura['emisor_nit'], 'Receptor' => $factura['receptor'] ?: 'Sin especificar', 'Identificación del receptor' => ($factura['receptor_id'] ?? '') ?: 'No indicada', 'Correo del receptor' => ($factura['receptor_correo'] ?? '') ?: 'No indicado', 'Envío por correo' => $textoCorreo, 'Subtotal' => pesos($factura['subtotal']), 'IVA' => pesos($factura['iva']), 'Total en COP' => pesos($factura['total']), 'Fecha de registro' => $factura['fecha_registro'], 'Código de seguridad SHA-256' => $factura['codigo_seguridad']] as $etiqueta => $valor): ?>
<div><dt><?= h($etiqueta) ?></dt><dd><?= h($valor) ?></dd></div><?php endforeach; ?></dl>
<p class="downloads"><a class="button ghost" href="/factura.php?numero=<?= rawurlencode($factura['numero']) ?>&amp;formato=pdf">Descargar PDF</a> <a class="button ghost" href="/factura.php?numero=<?= rawurlencode($factura['numero']) ?>&amp;formato=xml">Descargar XML (contenedor)</a> <a class="button ghost" href="/factura.php?numero=<?= rawurlencode($factura['numero']) ?>&amp;formato=factura">XML original</a> <a class="button ghost" href="/verificar.php?cufe=<?= h($factura['codigo_seguridad']) ?>">Verificar</a>
<?php if (($ec['estado'] ?? '') === 'CONTINGENCIA'): ?><form class="inline" method="post" action="/reintentar.php"><input type="hidden" name="numero" value="<?= h($factura['numero']) ?>"><button class="button" type="submit">Reintentar envío ahora</button></form><?php endif; ?></p></div>
<?php else: ?><p class="empty">Revisa el número de factura en el registro.</p><?php endif; endif; ?>
</main><footer class="wrap"><span>Folio · Laboratorio de Web Services</span><span>Simulación académica DIAN</span></footer>
</body></html>
