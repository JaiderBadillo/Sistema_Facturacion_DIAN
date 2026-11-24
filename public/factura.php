<?php
declare(strict_types=1);

// Descarga de una factura aprobada: ?numero=SETP123&formato=pdf|xml|factura
//   pdf     representación gráfica con código QR
//   xml     contenedor (AttachedDocument simulado): factura + respuesta de validación
//   factura XML original tal como lo envió el emisor
require_once __DIR__ . '/../src/bootstrap.php';

$repo = new Repositorio($config['data_dir']);
$numero = $_GET['numero'] ?? '';
$formato = $_GET['formato'] ?? 'pdf';
$factura = is_string($numero) ? $repo->obtenerFactura($numero) : null;
if (!$factura || !in_array($formato, ['pdf', 'xml', 'factura'], true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Factura no encontrada.';
    return;
}
if ($formato === 'pdf') {
    $contenido = Documentos::pdf($factura, $repo, $config);
    header('Content-Type: application/pdf');
    $nombre = $numero . '.pdf';
} else {
    $contenido = $formato === 'xml' ? Documentos::contenedor($factura, $repo, $config) : $repo->obtenerXml($numero);
    if ($contenido === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'El XML de esta factura no está disponible (se registró antes de conservar los XML).';
        return;
    }
    header('Content-Type: application/xml; charset=utf-8');
    $nombre = $numero . ($formato === 'xml' ? '-contenedor.xml' : '.xml');
}
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($contenido));
echo $contenido;
