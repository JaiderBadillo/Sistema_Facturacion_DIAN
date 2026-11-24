<?php
declare(strict_types=1);

// Envío directo del correo por la API HTTPS de Brevo (POST /v3/smtp/email), sin n8n ni SMTP.
// Se activa cuando config.php (o config.local.php) trae brevo_api_key y brevo_remitente_email.
final class NotificadorBrevo
{
    public function __construct(private array $config) {}

    public static function configurado(array $config): bool
    {
        return trim((string) ($config['brevo_api_key'] ?? '')) !== '' && trim((string) ($config['brevo_remitente_email'] ?? '')) !== '';
    }

    /** @return array{ok: bool, detalle: string} */
    public function enviar(array $f, string $pdf, string $contenedor): array
    {
        $payload = json_encode([
            'sender' => ['name' => $this->config['brevo_remitente_nombre'] ?? 'Folio Facturación', 'email' => $this->config['brevo_remitente_email']],
            'to' => [['email' => $f['receptor_correo'], 'name' => $f['receptor'] ?: $f['receptor_correo']]],
            'subject' => CorreoFactura::asunto($f),
            'htmlContent' => CorreoFactura::html($f),
            'attachment' => [
                ['name' => $f['numero'] . '.pdf', 'content' => base64_encode($pdf)],
                ['name' => $f['numero'] . '-contenedor.xml', 'content' => base64_encode($contenedor)],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $respuesta = @file_get_contents($this->config['brevo_url'], false, stream_context_create(['http' => [
            'method' => 'POST', 'content' => $payload, 'timeout' => 20, 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\naccept: application/json\r\napi-key: {$this->config['brevo_api_key']}\r\n",
        ]]));
        $estado = $http_response_header[0] ?? '';
        if ($respuesta === false || !preg_match('#\s2\d\d\s#', $estado . ' ')) {
            // Nunca se incluye la clave en el detalle; solo el estado HTTP y el mensaje de Brevo.
            return ['ok' => false, 'detalle' => $respuesta === false ? 'Sin respuesta de Brevo (¿hay internet?)' : trim($estado . ' ' . mb_substr($respuesta, 0, 200))];
        }
        $id = json_decode($respuesta, true)['messageId'] ?? '';
        return ['ok' => true, 'detalle' => 'Brevo aceptó el correo' . ($id !== '' ? " ({$id})" : '')];
    }
}
