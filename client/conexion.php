<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

function conectar(): SoapClient
{
    global $config;
    if (!extension_loaded('soap')) {
        throw new RuntimeException('Active soap en php.ini o use php -d extension=soap.');
    }
    $opciones = ['cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true, 'trace' => true, 'connection_timeout' => 5];
    if (getenv('FOLIO_USUARIO')) { // servidor protegido (por ejemplo en Render)
        $opciones += ['login' => getenv('FOLIO_USUARIO'), 'password' => (string) getenv('FOLIO_CLAVE')];
    }
    return new SoapClient($config['endpoint_url'] . '?wsdl', $opciones);
}

function xmlDeEjemplo(string $numero, string $subtotal, string $iva, string $total, string $receptor): string
{
    $factura = new SimpleXMLElement('<Factura/>');
    $factura->addChild('Numero', $numero);
    $factura->addChild('Fecha', date('Y-m-d'));
    $emisor = $factura->addChild('Emisor');
    $emisor->addChild('NIT', '900123456');
    $emisor->addChild('RazonSocial', 'Comercializadora Andina S.A.S.');
    $factura->addChild('Receptor')->addChild('Nombre', htmlspecialchars($receptor, ENT_XML1, 'UTF-8'));
    $totales = $factura->addChild('Totales');
    $totales->addChild('Subtotal', $subtotal);
    $totales->addChild('IVA', $iva);
    $totales->addChild('Total', $total);
    return $factura->asXML();
}

function numeroDisponible(SoapClient $cliente): string
{
    do {
        $numero = 'SETP' . random_int(990000001, 994999999);
        $estado = $cliente->ConsultarEstadoFactura(['numeroFactura' => $numero, 'nitEmisor' => '900123456']);
    } while ($estado->existe);
    return $numero;
}
