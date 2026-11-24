<?php
declare(strict_types=1);

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Acceso: con FOLIO_USUARIO y FOLIO_CLAVE la web y el servicio SOAP piden usuario y contraseña (HTTP Basic).
// En Render (variable RENDER) son obligatorias: sin ellas el sitio no responde, para no quedar abierto a cualquiera.
// Son públicos /verificar.php (el QR de la factura debe abrirse sin credenciales) y los archivos estáticos, que no tienen datos.
if (!in_array($ruta, ['/verificar.php', '/folio.css', '/emision.js', '/buscar.js'], true)) {
    $usuario = (string) getenv('FOLIO_USUARIO');
    $clave = (string) getenv('FOLIO_CLAVE');
    if ($usuario === '' && getenv('RENDER')) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Configure FOLIO_USUARIO y FOLIO_CLAVE en las variables de entorno para activar el sitio.';
        return;
    }
    if ($usuario !== '') {
        $okUsuario = hash_equals($usuario, $_SERVER['PHP_AUTH_USER'] ?? '');
        $okClave = hash_equals($clave, $_SERVER['PHP_AUTH_PW'] ?? '');
        if (!($okUsuario && $okClave)) {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="Folio", charset="UTF-8"');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Se requiere usuario y contraseña.';
            return;
        }
    }
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
