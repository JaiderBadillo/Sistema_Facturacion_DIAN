<?php
declare(strict_types=1);

// Destino del código QR de la factura: ?cufe=<código de seguridad>. Equivale, en esta simulación, al catálogo de verificación de la DIAN.
require_once __DIR__ . '/../src/bootstrap.php';

$repo = new Repositorio($config['data_dir']);
$cufe = is_string($_GET['cufe'] ?? null) ? strtolower(trim($_GET['cufe'])) : '';
$factura = null;
if (preg_match('/\A[0-9a-f]{64}\z/', $cufe)) {
    foreach ($repo->listarFacturas() as $f) {
        if (hash_equals($f['codigo_seguridad'], $cufe)) {
            $factura = $f;
            break;
        }
    }
}
if (!$factura) {
    http_response_code(404);
}
$h = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es-CO"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificación de factura · Folio</title><link rel="icon" href="data:,"><link rel="stylesheet" href="/folio.css"></head>
<body><header><div class="mast wrap"><a href="/index.php" class="brand"><i class="logomark" aria-hidden="true"></i>Folio<span>.</span></a></div></header>
<main class="wrap"><div class="heading inner"><div><p class="kicker">Verificación de factura electrónica</p><h1><?= $factura ? 'Factura válida' : 'No encontrada' ?></h1></div></div>
<?php if ($factura): ?>
<div class="receipt"><div class="receipt-head"><span class="status"><?= $h(ucfirst(strtolower($factura['estado']))) ?></span><span><?= $h($factura['fecha']) ?></span></div><dl>
<?php foreach (['Número' => $factura['numero'], 'Emisor' => $factura['emisor_nombre'] . ' (NIT ' . $factura['emisor_nit'] . ')', 'Receptor' => $factura['receptor'] ?: 'Sin especificar',
    'Total en COP' => '$' . number_format((float) $factura['total'], 2, ',', '.'), 'Fecha de validación' => $factura['fecha_registro'], 'Código de seguridad SHA-256' => $factura['codigo_seguridad']] as $e => $v): ?>
<div><dt><?= $h($e) ?></dt><dd><?= $h($v) ?></dd></div><?php endforeach; ?></dl></div>
<?php else: ?><p class="empty">Ninguna factura registrada coincide con ese código. Compruebe que el QR sea de este sistema.</p><?php endif; ?>
<p class="note">Simulación académica: esta página reemplaza, para el laboratorio, la consulta del catálogo de la DIAN.</p>
</main><footer class="wrap"><span>Folio · Laboratorio de Web Services</span><span>Simulación académica DIAN</span></footer></body></html>
