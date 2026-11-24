<?php
declare(strict_types=1);

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Acceso (ver src/acceso.php): formulario de ingreso en /login.php; Basic para el servicio SOAP.
require __DIR__ . '/src/acceso.php';
if (!acceso_controlar((string) $ruta)) {
    return;
}
switch ($ruta) {
    case '/':
    case '/index.php':
        require __DIR__ . '/public/index.php';
        break;
    case '/servicio.php':
        require __DIR__ . '/public/servicio.php';
        break;
    case '/factura.php':
        require __DIR__ . '/public/factura.php';
        break;
    case '/verificar.php':
        require __DIR__ . '/public/verificar.php';
        break;
    case '/reintentar.php':
        require __DIR__ . '/public/reintentar.php';
        break;
    case '/correo.php':
        require __DIR__ . '/public/correo.php';
        break;
    case '/folio.css':
    case '/emision.js':
    case '/buscar.js':
        header('Content-Type: ' . ($ruta === '/folio.css' ? 'text/css' : 'application/javascript') . '; charset=utf-8');
        readfile(__DIR__ . '/public' . $ruta);
        break;
    default:
        http_response_code(404);
        echo 'Recurso no encontrado.';
}
