<?php
declare(strict_types=1);

// Generador de código QR (modo byte, corrección de errores M, versiones 1 a 10) sin librerías.
// shortcut: solo modo byte y hasta ~213 bytes; para textos más largos o modos numérico/alfanumérico usar una librería.
final class CodigoQr
{
    // versión => [datos totales, EC por bloque, bloques grupo 1, datos por bloque g1, bloques grupo 2, datos por bloque g2]
    private const VERSIONES = [
        1 => [16, 10, 1, 16, 0, 0], 2 => [28, 16, 1, 28, 0, 0], 3 => [44, 26, 1, 44, 0, 0],
        4 => [64, 18, 2, 32, 0, 0], 5 => [86, 24, 2, 43, 0, 0], 6 => [108, 16, 4, 27, 0, 0],
        7 => [124, 18, 4, 31, 0, 0], 8 => [154, 22, 2, 38, 2, 39], 9 => [182, 22, 3, 36, 2, 37],
        10 => [216, 26, 4, 43, 1, 44],
    ];
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];
    private static array $exp = [];
    private static array $log = [];

    private static function campo(): void
    {
        if (self::$exp) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function mul(int $a, int $b): int
    {
        return $a && $b ? self::$exp[self::$log[$a] + self::$log[$b]] : 0;
    }

    private static function reedSolomon(array $datos, int $n): array
    {
        $gen = [1];
        for ($i = 0; $i < $n; $i++) {
            $nuevo = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $nuevo[$j] ^= $c;
                $nuevo[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $gen = $nuevo;
        }
        $resto = array_fill(0, $n, 0);
        foreach ($datos as $d) {
            $factor = $d ^ array_shift($resto);
            $resto[] = 0;
            if ($factor) {
                for ($k = 1; $k <= $n; $k++) {
                    $resto[$k - 1] ^= self::mul($gen[$k], $factor);
                }
            }
        }
        return $resto;
    }

    /** @return bool[][] matriz de módulos (true = oscuro) sin zona de silencio */
    public static function matriz(string $texto): array
    {
        self::campo();
        $bytes = array_values(unpack('C*', $texto) ?: []);
        $version = 0;
        foreach (self::VERSIONES as $v => $info) {
            if (4 + ($v < 10 ? 8 : 16) + 8 * count($bytes) <= $info[0] * 8) {
                $version = $v;
                break;
            }
        }
        if (!$version) {
            throw new LengthException('El texto es demasiado largo para el generador de QR.');
        }
        [$capacidad, $ecBloque, $b1, $d1, $b2, $d2] = self::VERSIONES[$version];

        // 1. Bits de datos: modo byte, longitud, datos, terminador y relleno
        $bits = '0100' . str_pad(decbin(count($bytes)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacidad * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $palabras = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($palabras) < $capacidad; $i++) {
            $palabras[] = $i % 2 ? 0x11 : 0xEC;
        }

        // 2. Bloques, corrección de errores e intercalado
        $bloques = [];
        $pos = 0;
        foreach ([[$b1, $d1], [$b2, $d2]] as [$cantidad, $largo]) {
            for ($i = 0; $i < $cantidad; $i++) {
                $bloques[] = array_slice($palabras, $pos, $largo);
                $pos += $largo;
            }
        }
        $ec = array_map(fn($b) => self::reedSolomon($b, $ecBloque), $bloques);
        $final = [];
        for ($i = 0; $i < max($d1, $d2); $i++) {
            foreach ($bloques as $b) {
                if (isset($b[$i])) {
                    $final[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecBloque; $i++) {
            foreach ($ec as $e) {
                $final[] = $e[$i];
            }
        }
        $flujo = '';
        foreach ($final as $w) {
            $flujo .= str_pad(decbin($w), 8, '0', STR_PAD_LEFT);
        }

        // 3. Patrones de función
        $n = 17 + 4 * $version;
        $m = array_fill(0, $n, array_fill(0, $n, false));
        $f = array_fill(0, $n, array_fill(0, $n, false));
        $poner = function (int $col, int $fila, bool $v) use (&$m, &$f, $n): void {
            if ($col >= 0 && $col < $n && $fila >= 0 && $fila < $n) {
                $m[$fila][$col] = $v;
                $f[$fila][$col] = true;
            }
        };
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$c0, $r0]) {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $dentro = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                    $oscuro = $dentro && ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6 || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4));
                    $poner($c0 + $dc, $r0 + $dr, $oscuro);
                }
            }
        }
        for ($i = 8; $i < $n - 8; $i++) {
            $poner($i, 6, $i % 2 === 0);
            $poner(6, $i, $i % 2 === 0);
        }
        $pa = self::ALINEACION[$version];
        foreach ($pa as $ri => $r) {
            foreach ($pa as $ci => $c) {
                if (($ri === 0 && $ci === 0) || ($ri === 0 && $ci === count($pa) - 1) || ($ri === count($pa) - 1 && $ci === 0)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $poner($c + $dc, $r + $dr, max(abs($dr), abs($dc)) !== 1);
                    }
                }
            }
        }
        $formato = function (int $mascara) use ($poner, $n): void {
            $datos = $mascara; // nivel M = 00
            $resto = $datos;
            for ($i = 0; $i < 10; $i++) {
                $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
            }
            $bits = (($datos << 10) | $resto) ^ 0x5412;
            $bit = fn(int $i): bool => (($bits >> $i) & 1) === 1;
            for ($i = 0; $i <= 5; $i++) {
                $poner(8, $i, $bit($i));
            }
            $poner(8, 7, $bit(6));
            $poner(8, 8, $bit(7));
            $poner(7, 8, $bit(8));
            for ($i = 9; $i < 15; $i++) {
                $poner(14 - $i, 8, $bit($i));
            }
            for ($i = 0; $i < 8; $i++) {
                $poner($n - 1 - $i, 8, $bit($i));
            }
            for ($i = 8; $i < 15; $i++) {
                $poner(8, $n - 15 + $i, $bit($i));
            }
            $poner(8, $n - 8, true);
        };
        $formato(0); // reserva las zonas; se reescriben con la máscara elegida
        if ($version >= 7) {
            $resto = $version;
            for ($i = 0; $i < 12; $i++) {
                $resto = ($resto << 1) ^ (($resto >> 11) * 0x1F25);
            }
            $bitsV = ($version << 12) | $resto;
            for ($i = 0; $i < 18; $i++) {
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $poner($a, $b, (($bitsV >> $i) & 1) === 1);
                $poner($b, $a, (($bitsV >> $i) & 1) === 1);
            }
        }

        // 4. Colocar los datos en zigzag
        $k = 0;
        $arriba = true;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $n; $i++) {
                $fila = $arriba ? $n - 1 - $i : $i;
                foreach ([$col, $col - 1] as $c) {
                    if (!$f[$fila][$c]) {
                        $m[$fila][$c] = ($flujo[$k++] ?? '0') === '1';
                    }
                }
            }
            $arriba = !$arriba;
        }

        // 5. Elegir la máscara con menor penalización
        $mejor = null;
        $mejorPenal = PHP_INT_MAX;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $t = $m;
            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if (!$f[$r][$c] && self::mascara($mascara, $r, $c)) {
                        $t[$r][$c] = !$t[$r][$c];
                    }
                }
            }
            $penal = self::penalizacion($t, $n);
            if ($penal < $mejorPenal) {
                [$mejorPenal, $mejor, $elegida] = [$penal, $t, $mascara];
            }
        }
        $m = $mejor; // $poner y $formato escriben sobre esta misma variable (captura por referencia)
        $formato($elegida);
        return $m;
    }

    private static function mascara(int $k, int $r, int $c): bool
    {
        return match ($k) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
            6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
            default => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
        };
    }

    private static function penalizacion(array $t, int $n): int
    {
        $p = 0;
        $lineas = [];
        for ($r = 0; $r < $n; $r++) {
            $lineas[] = implode('', array_map(fn($v) => $v ? '1' : '0', $t[$r]));
        }
        for ($c = 0; $c < $n; $c++) {
            $lineas[] = implode('', array_map(fn($row) => $row[$c] ? '1' : '0', $t));
        }
        foreach ($lineas as $l) {
            if (preg_match_all('/0{5,}|1{5,}/', $l, $mm)) {
                foreach ($mm[0] as $racha) {
                    $p += 3 + strlen($racha) - 5;
                }
            }
            $p += 40 * (preg_match_all('/(?=10111010000)/', $l) + preg_match_all('/(?=00001011101)/', $l));
        }
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                if ($t[$r][$c] === $t[$r][$c + 1] && $t[$r][$c] === $t[$r + 1][$c] && $t[$r][$c] === $t[$r + 1][$c + 1]) {
                    $p += 3;
                }
            }
        }
        $oscuros = 0;
        foreach ($t as $row) {
            $oscuros += count(array_filter($row));
        }
        $total = $n * $n;
        return $p + (int) (ceil(abs($oscuros * 20 - $total * 10) / $total) - 1) * 10;
    }
}
