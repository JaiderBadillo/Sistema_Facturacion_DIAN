<?php
declare(strict_types=1);

require __DIR__ . '/conexion.php';
try {
    $cliente = conectar();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\nInicie el servidor en localhost:8080.\n");
    exit(1);
}
echo "FOLIO  /  Cliente SOAP de facturación\n";
while (true) {
    echo "\n1 Rangos    2 Enviar factura    3 Probar error de IVA    4 Consultar estado    5 Salir\nOpción: ";
    $entrada = fgets(STDIN);
    if ($entrada === false || trim($entrada) === '5') {
        break;
    }
    try {
        switch ($opcion = trim($entrada)) {
            case '1':
                $r = $cliente->ConsultarRangosNumeracion(['nit' => '900123456']);
                echo "\n{$r->mensaje}\nPrefijo {$r->prefijo} | {$r->desde} a {$r->hasta}\nResolución {$r->resolucion} | Vigencia {$r->fechaVigencia}\n";
                break;
            case '2':
            case '3':
                $numero = numeroDisponible($cliente);
                $total = $opcion === '2' ? '119000.00' : '150000.00';
                $xml = xmlDeEjemplo($numero, '100000.00', '19000.00', $total, 'Librería del Centro');
                $r = $cliente->EnviarFactura(['nitEmisor' => '900123456', 'facturaXml' => $xml]);
                echo "\n{$r->estado} · {$r->codigo} | {$r->mensaje}\n";
                if ($r->codigoSeguridad !== '') {
                    echo "SHA-256: {$r->codigoSeguridad}\n";
                }
                echo "Validación: {$r->fechaValidacion}\n";
                break;
            case '4':
                echo 'Número de factura: ';
                $numero = trim((string) fgets(STDIN));
                echo 'NIT del emisor [900123456]: ';
                $nit = trim((string) fgets(STDIN)) ?: '900123456';
                $r = $cliente->ConsultarEstadoFactura(['numeroFactura' => $numero, 'nitEmisor' => $nit]);
                echo "\n{$r->numeroFactura} | {$r->estado}\n";
                if ($r->existe) {
                    echo 'Total COP $' . number_format($r->totalFactura, 2, ',', '.') . " | Registro {$r->fechaRegistro}\n";
                }
                break;
            default:
                echo "Elija una opción entre 1 y 5.\n";
        }
    } catch (SoapFault $e) {
        echo "Fallo SOAP [{$e->faultcode}] {$e->getMessage()}\n";
    }
}
