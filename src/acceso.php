<?php
declare(strict_types=1);

// Acceso a Folio. Con FOLIO_USUARIO y FOLIO_CLAVE la web pide ingresar en /login.php (cookie firmada de 12 h);
// el servicio SOAP y curl siguen entrando con HTTP Basic. En Render (variable RENDER) son obligatorias:
// sin ellas el sitio no responde, para no quedar abierto. Sin variables y fuera de Render (uso local) no pide nada.
// shortcut: un solo usuario y sin límite de intentos más allá de una pausa; para varios usuarios, usar una base de datos.

const ACCESO_PUBLICAS = ['/verificar.php', '/folio.css', '/emision.js', '/buscar.js']; // el QR y los estáticos no llevan datos

function acceso_firma(string $vence, string $usuario): string
{
    // La clave entra en la llave: al cambiar la contraseña, las sesiones anteriores dejan de valer.
    $llave = hash('sha256', 'folio|' . (getenv('FOLIO_TOKEN') ?: 'folio-uts-2026') . '|' . trim((string) getenv('FOLIO_CLAVE')));
    return hash_hmac('sha256', $vence . '|' . $usuario, $llave);
}

function acceso_cookie_valida(string $usuario): bool
{
    return preg_match('/\A(\d{10})\.([0-9a-f]{64})\z/', (string) ($_COOKIE['folio_sesion'] ?? ''), $m) === 1
        && (int) $m[1] > time() && hash_equals(acceso_firma($m[1], $usuario), $m[2]);
}

function acceso_credenciales_validas(string $usuario, string $clave, string $u, string $c): bool
{
    $okU = hash_equals($usuario, trim($u));
    $okC = hash_equals($clave, trim($c));
    return $okU && $okC;
}

function acceso_pagina_login(string $siguiente, ?string $error): void
{
    $h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es-CO"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Ingresar · Folio</title><link rel="icon" href="data:,"><link rel="stylesheet" href="/folio.css"></head><body>'
        . '<header><div class="mast wrap"><span class="brand"><i class="logomark" aria-hidden="true"></i>Folio<span>.</span></span></div></header>'
        . '<main class="wrap"><div class="heading inner"><div><p class="kicker">Facturación electrónica</p><h1>Ingresar</h1></div></div>'
        . '<form method="post" action="/login.php?siguiente=' . rawurlencode($siguiente) . '" style="max-width:420px">'
        . '<label>Usuario<input name="usuario" autocomplete="username" required autofocus></label>'
        . '<label style="margin-top:14px">Contraseña<input type="password" name="clave" autocomplete="current-password" required></label>'
        . ($error ? '<p role="alert">' . $h($error) . '</p>' : '')
        . '<div class="actions" style="margin-top:18px"><button class="button" type="submit">Entrar</button></div></form></main></body></html>';
}

/** Devuelve true si la petición puede continuar; false si ya se respondió (login, redirección, 401, 503). */
function acceso_controlar(string $ruta): bool
{
    $usuario = trim((string) getenv('FOLIO_USUARIO'));
    $clave = trim((string) getenv('FOLIO_CLAVE'));
    if ($usuario === '' || $clave === '') {
        if (getenv('RENDER')) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Configure FOLIO_USUARIO y FOLIO_CLAVE en las variables de entorno para activar el sitio.';
            return false;
        }
        return true; // uso local sin contraseña
    }
    if (in_array($ruta, ACCESO_PUBLICAS, true)) {
        return true;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $opciones = ['path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];

    if ($ruta === '/salir.php') {
        setcookie('folio_sesion', '', $opciones + ['expires' => 1]);
        header('Location: /login.php');
        http_response_code(302);
        return false;
    }
    if ($ruta === '/login.php') {
        $siguiente = $_GET['siguiente'] ?? '/';
        $siguiente = is_string($siguiente) && preg_match('#\A/(?![/\\\\])#', $siguiente) === 1 ? $siguiente : '/'; // solo rutas internas
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (acceso_credenciales_validas($usuario, $clave, (string) ($_POST['usuario'] ?? ''), (string) ($_POST['clave'] ?? ''))) {
                $vence = (string) (time() + 12 * 3600);
                setcookie('folio_sesion', $vence . '.' . acceso_firma($vence, $usuario), $opciones + ['expires' => (int) $vence]);
                header('Location: ' . $siguiente);
                http_response_code(302);
                return false;
            }
            usleep(600000); // frena la adivinanza de contraseñas
            $error = 'Usuario o contraseña incorrectos.';
        }
        acceso_pagina_login($siguiente, $error);
        return false;
    }
    $basicValido = isset($_SERVER['PHP_AUTH_USER']) && acceso_credenciales_validas($usuario, $clave, (string) $_SERVER['PHP_AUTH_USER'], (string) ($_SERVER['PHP_AUTH_PW'] ?? ''));
    if ($basicValido || acceso_cookie_valida($usuario)) {
        return true;
    }
    // Un navegador que pide una página va al formulario; el servicio SOAP y curl reciben 401 con HTTP Basic.
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $ruta !== '/servicio.php' && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        header('Location: /login.php?siguiente=' . rawurlencode($_SERVER['REQUEST_URI']));
        http_response_code(302);
        return false;
    }
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="Folio", charset="UTF-8"');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Se requiere usuario y contraseña.';
    return false;
}
