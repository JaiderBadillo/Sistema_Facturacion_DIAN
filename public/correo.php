<?php
declare(strict_types=1);

// Callback de n8n: informa si el correo se envió (ENVIADO) o falló (FALLIDO).
// Cabecera obligatoria X-Folio-Token con el secreto de config.php.
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"error":"Use POST"}';
    return;
}
if (!hash_equals($config['correo_token'], $_SERVER['HTTP_X_FOLIO_TOKEN'] ?? '')) {
    http_response_code(403);
    echo '{"error":"Token inválido"}';
    return;
}
$datos = json_decode((string) file_get_contents('php://input'), true);
$repo = new Repositorio($config['data_dir']);
$numero = is_array($datos) && is_string($datos['numero'] ?? null) ? $datos['numero'] : '';
$estado = is_array($datos) ? ($datos['estado'] ?? '') : '';
if (!in_array($estado, ['ENVIADO', 'FALLIDO'], true) || !$repo->existeFactura($numero)) {
    http_response_code(422);
    echo '{"error":"Datos inválidos o factura inexistente"}';
    return;
}
// Si n8n agotó sus reintentos, la factura entra en contingencia y Folio sigue reintentando hasta el plazo.
$repo->guardarEstadoCorreo($numero, [
    'estado' => $estado === 'FALLIDO' ? 'CONTINGENCIA' : $estado, 'fecha' => date('Y-m-d H:i:s'), 'detalle' => mb_substr((string) ($datos['detalle'] ?? ''), 0, 300),
]);
echo '{"ok":true}';
