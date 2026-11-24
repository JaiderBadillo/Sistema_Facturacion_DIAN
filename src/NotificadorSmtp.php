<?php
declare(strict_types=1);

// Envío directo por SMTP (Gmail u otro) con un cliente mínimo: conexión TLS, AUTH PLAIN y mensaje MIME con adjuntos.
// smtp_seguridad: 'ssl' (puerto 465), 'starttls' (587) o 'none' (solo para pruebas locales).
// shortcut: sin cola ni reintentos propios (los da EnvioCorreo) y solo AUTH PLAIN; para volumen o OAuth usar una librería.
final class NotificadorSmtp
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(private array $config) {}

    public static function configurado(array $c): bool
    {
        foreach (['smtp_host', 'smtp_usuario', 'smtp_clave', 'smtp_remitente'] as $k) {
            if (trim((string) ($c[$k] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /** @return array{0: int, 1: string} */
    private function leer(): array
    {
        $texto = '';
        while (($linea = fgets($this->socket, 1024)) !== false) {
            $texto .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') {
                break;
            }
        }
        if ($texto === '') {
            throw new RuntimeException('El servidor SMTP no respondió (tiempo agotado).');
        }
        return [(int) substr($texto, 0, 3), trim($texto)];
    }

    // Los errores solo incluyen la respuesta del servidor, nunca la orden enviada (AUTH lleva la clave).
    private function orden(string $linea, int ...$esperados): string
    {
        if ($linea !== '') {
            fwrite($this->socket, $linea . "\r\n");
        }
        [$codigo, $texto] = $this->leer();
        if (!in_array($codigo, $esperados, true)) {
            throw new RuntimeException('SMTP ' . $texto);
        }
        return $texto;
    }

    private function conectar(): void
    {
        $host = $this->config['smtp_host'];
        $puerto = (int) ($this->config['smtp_puerto'] ?? 465);
        $seguridad = $this->config['smtp_seguridad'] ?? 'ssl';
        $this->socket = @stream_socket_client(($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . "{$host}:{$puerto}", $codigo, $mensaje, 15, STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]));
        if (!$this->socket) {
            throw new RuntimeException("No se pudo conectar a {$host}:{$puerto} ({$mensaje})");
        }
        stream_set_timeout($this->socket, 20);
        $this->orden('', 220);
        $this->orden('EHLO folio.local', 250);
        if ($seguridad === 'starttls') {
            $this->orden('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se pudo establecer la conexión TLS con el servidor SMTP.');
            }
            $this->orden('EHLO folio.local', 250);
        }
        $this->orden('AUTH PLAIN ' . base64_encode("\0{$this->config['smtp_usuario']}\0{$this->config['smtp_clave']}"), 235);
    }

    private function cerrar(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    /** Conecta y se autentica sin enviar ningún correo. @return array{ok: bool, detalle: string} */
    public function verificar(): array
    {
        try {
            $this->conectar();
            return ['ok' => true, 'detalle' => 'Conexión y autenticación correctas'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detalle' => $e->getMessage()];
        } finally {
            $this->cerrar();
        }
    }

    private function adjunto(string $limite, string $nombre, string $tipo, string $contenido): string
    {
        return "--{$limite}\r\nContent-Type: {$tipo}; name=\"{$nombre}\"\r\nContent-Disposition: attachment; filename=\"{$nombre}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($contenido), 76, "\r\n");
    }

    private function mensaje(array $f, string $pdf, string $contenedor): string
    {
        $limite = 'folio_' . bin2hex(random_bytes(8));
        $de = $this->config['smtp_remitente'];
        $b64 = fn(string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $cabeceras = [
            'Date: ' . date('r'),
            'From: ' . $b64($this->config['smtp_remitente_nombre'] ?? 'Folio Facturación') . " <{$de}>",
            'To: ' . $b64($f['receptor'] ?: $f['receptor_correo']) . " <{$f['receptor_correo']}>",
            'Subject: ' . $b64(CorreoFactura::asunto($f)),
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . substr((string) strrchr($de, '@'), 1) . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/mixed; boundary=\"{$limite}\"",
        ];
        return implode("\r\n", $cabeceras) . "\r\n\r\n"
            . "--{$limite}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode(CorreoFactura::html($f)), 76, "\r\n")
            . $this->adjunto($limite, $f['numero'] . '.pdf', 'application/pdf', $pdf)
            . $this->adjunto($limite, $f['numero'] . '-contenedor.xml', 'application/xml', $contenedor)
            . "--{$limite}--\r\n";
    }

    /** @return array{ok: bool, detalle: string} */
    public function enviar(array $f, string $pdf, string $contenedor): array
    {
        try {
            $this->conectar();
            $this->orden("MAIL FROM:<{$this->config['smtp_remitente']}>", 250);
            $this->orden("RCPT TO:<{$f['receptor_correo']}>", 250, 251);
            $this->orden('DATA', 354);
            $cuerpo = str_replace("\r\n.", "\r\n..", $this->mensaje($f, $pdf, $contenedor));
            $respuesta = $this->orden($cuerpo . "\r\n.", 250);
            return ['ok' => true, 'detalle' => 'SMTP aceptó el correo (' . mb_substr($respuesta, 0, 80) . ')'];
        } catch (Throwable $e) {
            return ['ok' => false, 'detalle' => $e->getMessage()];
        } finally {
            $this->cerrar();
        }
    }
}
