<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['wsdl']) || isset($_GET['WSDL']))) {
    header('Content-Type: text/xml; charset=utf-8');
    // La dirección del servicio en el contrato debe ser la URL pública, no la de localhost.
    echo preg_replace_callback('#(<soap:address location=")[^"]*(")#', fn($m) => $m[1] . htmlspecialchars($config['endpoint_url'], ENT_XML1) . $m[2], file_get_contents($config['wsdl_path']));
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo 'Consulte el contrato con ?wsdl o envíe el sobre SOAP mediante POST.';
    return;
}
if (!extension_loaded('soap')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Active la extensión soap en PHP para recibir peticiones.';
    return;
}
$servidor = new SoapServer($config['wsdl_path'], [
    'soap_version' => SOAP_1_1, 'cache_wsdl' => WSDL_CACHE_NONE, 'encoding' => 'UTF-8',
]);
$servidor->setClass(FacturacionService::class);
$servidor->handle();
