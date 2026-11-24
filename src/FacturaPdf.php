<?php
declare(strict_types=1);

// Representación gráfica (PDF) de la factura, escrita a mano en PDF 1.4 para no depender de librerías.
// shortcut: sin logos ni tipografías propias; si se necesitan, usar una librería (dompdf, TCPDF) al migrar a producción.
final class FacturaPdf
{
    private const VERDE = [0.09, 0.29, 0.23];
    private const TINTA = [0.11, 0.11, 0.10];
    private const GRIS = [0.42, 0.42, 0.40];
    private string $c = '';

    private function texto(float $x, float $y, string $t, int $tam = 10, bool $negrita = false, array $color = self::TINTA): void
    {
        $t = mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        $t = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
        $this->c .= sprintf("BT /%s %d Tf %.2F %.2F %.2F rg %.2F %.2F Td (%s) Tj ET\n", $negrita ? 'F2' : 'F1', $tam, $color[0], $color[1], $color[2], $x, $y, $t);
    }

    private function derecha(float $xDer, float $y, string $t, int $tam, bool $negrita = false): void
    {
        $ancho = 0.0;
        foreach (str_split($t) as $ch) {
            $ancho += ctype_digit($ch) || $ch === '$' ? 0.556 : 0.278;
        }
        $this->texto($xDer - $ancho * $tam, $y, $t, $tam, $negrita);
    }

    private function rect(float $x, float $y, float $w, float $h, ?array $relleno, bool $borde = false): void
    {
        $op = '';
        if ($relleno) {
            $this->c .= vsprintf("%.2F %.2F %.2F rg\n", $relleno);
            $op = 'f';
        }
        if ($borde) {
            $this->c .= "0.72 0.72 0.70 RG 0.6 w\n";
            $op = $relleno ? 'B' : 'S';
        }
        $this->c .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $y, $w, $h, $op);
    }

    // Dibuja el QR con zona de silencio de 4 módulos; une los módulos oscuros contiguos de cada fila en un solo rectángulo.
    private function qr(float $x, float $y, float $lado, string $contenido): void
    {
        $m = CodigoQr::matriz($contenido);
        $n = count($m);
        $mod = $lado / ($n + 8);
        $this->c .= sprintf("1 1 1 rg %.2F %.2F %.2F %.2F re f
0 0 0 rg
", $x, $y, $lado, $lado);
        foreach ($m as $r => $fila) {
            for ($c = 0; $c < $n; $c++) {
                if (!$fila[$c]) {
                    continue;
                }
                $ini = $c;
                while ($c + 1 < $n && $fila[$c + 1]) {
                    $c++;
                }
                $this->c .= sprintf("%.3F %.3F %.3F %.3F re f
", $x + (4 + $ini) * $mod, $y + $lado - (5 + $r) * $mod, ($c - $ini + 1) * $mod, $mod);
            }
        }
    }

    private static function pesos(float|int $v): string
    {
        return '$' . number_format((float) $v, 2, ',', '.');
    }

    public function generar(array $f, array $rango, string $urlVerificacion): string
    {
        $this->c = '';
        $blanco = [1, 1, 1];
        $this->rect(0, 752, 595, 90, self::VERDE);
        $this->texto(40, 800, 'FACTURA ELECTRÓNICA DE VENTA', 19, true, $blanco);
        $this->texto(40, 774, 'No. ' . $f['numero'], 14, false, $blanco);
        $this->texto(440, 800, 'Estado: ' . $f['estado'], 11, true, $blanco);
        $this->texto(440, 774, 'Fecha: ' . $f['fecha'], 11, false, $blanco);

        // Emisor y adquirente
        foreach ([[40, 'EMISOR'], [305, 'ADQUIRENTE']] as [$x, $titulo]) {
            $this->rect($x, 620, 250, 105, null, true);
            $this->texto($x + 10, 708, $titulo, 8, true, self::VERDE);
        }
        $this->texto(50, 690, $f['emisor_nombre'], 12, true);
        $this->texto(50, 672, 'NIT ' . $f['emisor_nit'], 10);
        $this->texto(50, 656, 'Resolución ' . $rango['resolucion'], 9, false, self::GRIS);
        $this->texto(50, 643, 'Prefijo ' . $rango['prefijo'] . ' · Rango ' . $rango['desde'] . ' a ' . $rango['hasta'], 9, false, self::GRIS);
        $this->texto(50, 630, 'Vigencia hasta ' . $rango['fecha_vigencia'], 9, false, self::GRIS);

        $this->texto(315, 690, $f['receptor'] ?: 'Sin especificar', 12, true);
        $this->texto(315, 672, 'Identificación ' . (($f['receptor_id'] ?? '') ?: 'No indicada'), 10);
        $this->texto(315, 656, 'Correo ' . (($f['receptor_correo'] ?? '') ?: 'No indicado'), 9, false, self::GRIS);

        // Importes
        $this->rect(40, 560, 515, 24, [0.93, 0.92, 0.88]);
        $this->texto(50, 568, 'Descripción', 9, true);
        $this->texto(512, 568, 'Valor', 9, true);
        $this->texto(50, 538, 'Venta de bienes y servicios', 10);
        $this->derecha(545, 538, self::pesos($f['subtotal']), 10);
        $this->texto(50, 516, 'IVA', 10);
        $this->derecha(545, 516, self::pesos($f['iva']), 10);
        $this->c .= "0.72 0.72 0.70 RG 0.6 w 40 500 m 555 500 l S\n";
        $this->texto(50, 478, 'TOTAL A PAGAR (COP)', 12, true);
        $this->derecha(545, 478, self::pesos($f['total']), 14, true);

        // Trazabilidad
        $this->texto(40, 420, 'CÓDIGO DE SEGURIDAD (SHA-256, equivalente al CUFE de esta simulación)', 8, true, self::VERDE);
        $this->texto(40, 405, $f['codigo_seguridad'], 8);
        $this->texto(40, 385, 'Fecha de registro: ' . $f['fecha_registro'], 9, false, self::GRIS);

        $this->qr(40, 255, 115, $urlVerificacion);
        $this->texto(170, 340, 'VERIFIQUE ESTA FACTURA', 8, true, self::VERDE);
        $this->texto(170, 326, 'Escanee el código QR para consultar su validez.', 9);
        $this->texto(170, 312, mb_substr($urlVerificacion, 0, 66), 7, false, self::GRIS);
        $this->texto(170, 303, mb_substr($urlVerificacion, 66), 7, false, self::GRIS);

        $this->texto(40, 70, 'Representación gráfica de la factura electrónica. El documento con validez es el archivo XML que se adjunta.', 8, false, self::GRIS);
        $this->texto(40, 58, 'Simulación académica (UTS · Web Services). Sin validez ante la DIAN.', 8, false, self::GRIS);

        $obj = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => '<< /Length ' . strlen($this->c) . " >>\nstream\n{$this->c}endstream",
        ];
        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($obj as $n => $cuerpo) {
            $pos[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$cuerpo}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach ($pos as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        return $pdf . "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
