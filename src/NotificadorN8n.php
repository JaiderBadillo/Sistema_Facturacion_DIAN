<?php
declare(strict_types=1);

// Avisa a n8n (webhook) que hay una factura aprobada para enviar por correo.
// n8n descarga el PDF y el XML desde las URLs del payload y los envía por SMTP.
final class NotificadorN8n
{
    public function __construct(private array $config) {}

    /** @return array{ok: bool, detalle: string} */
    public function enviar(array $f): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $n = rawurlencode($f['numero']);
        $payload = json_encode([
            'evento' => 'factura.aprobada',
            'numero' => $f['numero'],
            'fecha' => $f['fecha'],
            'emisor' => ['nit' => $f['emisor_nit'], 'nombre' => $f['emisor_nombre']],
            'receptor' => ['identificacion' => $f['receptor_id'], 'nombre' => $f['receptor'], 'correo' => $f['receptor_correo']],
            'subtotal' => $f['subtotal'], 'iva' => $f['iva'], 'total' => $f['total'],
            'cufe' => $f['codigo_seguridad'],
            'urls' => ['pdf' => "{$base}/factura.php?numero={$n}&formato=pdf", 'xml' => "{$base}/factura.php?numero={$n}&formato=xml", 'facturaXml' => "{$base}/factura.php?numero={$n}&formato=factura"],
            'callback' => ['url' => "{$base}/correo.php", 'token' => $this->config['correo_token']],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $respuesta = @file_get_contents($this->config['n8n_webhook_url'], false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload,
            'timeout' => 5, 'ignore_errors' => true,
        ]]));
        $estado = $http_response_header[0] ?? '';
        if ($respuesta === false || !preg_match('#\s2\d\d\s#', $estado . ' ')) {
            return ['ok' => false, 'detalle' => $estado ?: 'Sin respuesta de n8n en ' . $this->config['n8n_webhook_url']];
        }
        return ['ok' => true, 'detalle' => $estado];
    }
}
