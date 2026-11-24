<?php
declare(strict_types=1);

// Contingencia del correo. Dos usos:
//  1) n8n (flujo "Reintentar envíos pendientes") hace POST con la cabecera X-Folio-Token: reintenta todas las pendientes.
//  2) El botón "Reintentar envío ahora" de la ficha hace POST con numero: reintenta solo esa factura.
require_once __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    return;
}
$repo = new Repositorio($config['data_dir']);
if (hash_equals($config['correo_token'], $_SERVER['HTTP_X_FOLIO_TOKEN'] ?? '')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(EnvioCorreo::reintentarPendientes($repo, $config));
    return;
}
$numero = is_string($_POST['numero'] ?? null) ? $_POST['numero'] : '';
$estado = $repo->obtenerEstadoCorreo($numero);
if (($estado['estado'] ?? '') !== 'CONTINGENCIA') {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo se pueden reintentar facturas en contingencia.';
    return;
}
EnvioCorreo::reintentarPendientes($repo, $config, $numero);
header('Location: /index.php?vista=detalle&numero=' . rawurlencode($numero));
