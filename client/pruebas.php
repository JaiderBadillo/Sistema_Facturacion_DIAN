<?php
declare(strict_types=1);

require __DIR__ . '/conexion.php';
try {
    $cliente = conectar();
    $numero = numeroDisponible($cliente);
    $segundo = numeroDisponible($cliente);
} catch (Throwable $e) {
    fwrite(STDERR, "Error de conexión: {$e->getMessage()}\n");
    exit(1);
}
$xml = xmlDeEjemplo($numero, '200000.00', '38000.00', '238000.00', 'Taller La Estación');
$enviar = fn($doc) => $cliente->EnviarFactura(['nitEmisor' => '900123456', 'facturaXml' => $doc]);
$pruebas = [
    'Rango autorizado para el NIT 900123456' => function () use ($cliente) {
        $r = $cliente->ConsultarRangosNumeracion(['nit' => '900123456']);
        return $r->valido && $r->prefijo === 'SETP' && $r->resolucion === 'RES-18760000001';
    },
    'NIT sin resolución de numeración' => fn() => !$cliente->ConsultarRangosNumeracion(['nit' => '111222333'])->valido,
    "Aprobación y código SHA-256 de {$numero}" => function () use ($enviar, $xml, $numero) {
        $r = $enviar($xml);
        $hash = hash('sha256', $numero . '|900123456|238000.00|' . (string) simplexml_load_string($xml)->Fecha);
        return $r->estado === 'APROBADA' && $r->codigo === '200' && $r->codigoSeguridad === $hash;
    },
    'Rechazo de la misma factura al reenviarla' => function () use ($enviar, $xml) {
        $r = $enviar($xml);
        return $r->estado === 'RECHAZADA' && $r->codigo === '422' && str_contains($r->mensaje, 'ya fue registrada');
    },
    'Rechazo de un total que no coincide con subtotal e IVA' => function () use ($enviar, $segundo, $numero) {
        $n = $segundo !== $numero ? $segundo : $numero . 'A';
        $r = $enviar(xmlDeEjemplo($n, '100000.00', '19000.00', '999999.00', 'Taller La Estación'));
        return $r->estado === 'RECHAZADA' && str_contains($r->mensaje, 'subtotal');
    },
    'Consulta del estado y total de la factura aprobada' => function () use ($cliente, $numero) {
        $r = $cliente->ConsultarEstadoFactura(['numeroFactura' => $numero, 'nitEmisor' => '900123456']);
        return $r->existe && $r->estado === 'APROBADA' && $r->totalFactura == 238000 && $r->numeroFactura === $numero;
    },
];

echo "FOLIO  /  Pruebas de integración SOAP\n\n";
$exitos = 0;
foreach ($pruebas as $nombre => $probar) {
    try {
        $ok = $probar();
        $detalle = '';
    } catch (Throwable $e) {
        $ok = false;
        $detalle = ' · ' . $e->getMessage();
    }
    $exitos += (int) $ok;
    $marca = $ok ? 'OK' : 'FALLO';
    if ($ok && function_exists('stream_isatty') && stream_isatty(STDOUT)) {
        $marca = "\033[32mOK\033[0m";
    }
    echo "[{$marca}] {$nombre}{$detalle}\n";
}
printf("\n%d/6 pruebas exitosas (%d %%)\n", $exitos, round($exitos * 100 / 6));
exit($exitos === 6 ? 0 : 1);
