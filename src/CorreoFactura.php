<?php
declare(strict_types=1);

// Asunto y cuerpo HTML del correo de una factura; los comparten los envíos por SMTP y por Brevo.
final class CorreoFactura
{
    public static function asunto(array $f): string
    {
        return "Factura electrónica {$f['numero']} · {$f['emisor_nombre']}";
    }

    public static function html(array $f): string
    {
        $h = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fila = fn(string $k, string $v, bool $ultima = false): string => '<tr><td style="padding:6px;' . ($ultima ? '' : 'border-bottom:1px solid #ddd') . '">' . $k . '</td><td style="padding:6px;' . ($ultima ? 'font-size:11px;word-break:break-all' : 'border-bottom:1px solid #ddd') . '">' . $v . '</td></tr>';
        return '<div style="font-family:Georgia,serif;color:#1d1d1b;max-width:560px">'
            . '<h2 style="margin:0 0 8px">Factura electrónica ' . $h($f['numero']) . '</h2>'
            . '<p>Hola ' . $h($f['receptor']) . ',</p>'
            . '<p><strong>' . $h($f['emisor_nombre']) . '</strong> (NIT ' . $h($f['emisor_nit']) . ') le envía su factura electrónica de venta.</p>'
            . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
            . $fila('Número', '<strong>' . $h($f['numero']) . '</strong>') . $fila('Fecha de emisión', $h($f['fecha']))
            . $fila('Total', '<strong>$' . number_format((float) $f['total'], 2, ',', '.') . '</strong>') . $fila('Código de seguridad', $h($f['codigo_seguridad']), true)
            . '</table><p>Adjuntamos dos archivos: el <strong>PDF</strong> (representación gráfica) y el <strong>XML contenedor</strong> (la factura junto con su respuesta de validación), que es el documento con validez. Conserve ambos. El código QR del PDF permite verificar la factura.</p>'
            . '<p style="color:#777;font-size:12px">Mensaje automático de Folio, simulación académica de facturación electrónica (UTS). No responda a este correo.</p></div>';
    }
}
