<?php
declare(strict_types=1);

ini_set('soap.wsdl_cache_enabled', '0');
ini_set('soap.wsdl_cache_ttl', '0');
date_default_timezone_set('America/Bogota');

// URL pública: FOLIO_BASE_URL, o la que Render entrega en RENDER_EXTERNAL_URL, o localhost. De ella salen el QR, los enlaces y el WSDL.
$url = rtrim(getenv('FOLIO_BASE_URL') ?: getenv('RENDER_EXTERNAL_URL') ?: 'http://localhost:8080', '/');

$base = [
    'app_name' => 'Folio · Facturación electrónica',
    'version' => '2.0.0',
    'base_url' => $url,
    'endpoint_url' => $url . '/servicio.php',
    'wsdl_path' => __DIR__ . '/wsdl/FacturacionService.wsdl',
    'data_dir' => getenv('FOLIO_DATA_DIR') ?: __DIR__ . '/data/facturas', // en Render con disco: /var/data/facturas
    // Envío de facturas por correo mediante n8n (ver n8n/LEEME_N8N.md).
    'n8n_webhook_url' => getenv('FOLIO_N8N_WEBHOOK') ?: 'http://localhost:5678/webhook/folio-factura',
    'correo_token' => getenv('FOLIO_TOKEN') ?: 'folio-uts-2026', // secreto compartido con n8n; cámbielo fuera del laboratorio
    // Envío directo por Brevo (API HTTPS), igual que en Vincula. Si hay clave y remitente se usa en lugar de n8n.
    'brevo_api_key' => getenv('FOLIO_BREVO_KEY') ?: '',
    'brevo_remitente_email' => getenv('FOLIO_BREVO_REMITENTE') ?: '', // debe estar verificado en Brevo
    'brevo_remitente_nombre' => 'Folio Facturación',
    'brevo_url' => 'https://api.brevo.com/v3/smtp/email',
    'max_correos_dia' => (int) (getenv('FOLIO_MAX_CORREOS_DIA') ?: 30), // tope de correos por día con Brevo o SMTP (Brevo gratis permite 300)
    // Envío directo por SMTP (por ejemplo Gmail con contraseña de aplicación). Se usa si no hay clave de Brevo.
    'smtp_host' => getenv('FOLIO_SMTP_HOST') ?: '',
    'smtp_puerto' => 465,
    'smtp_seguridad' => 'ssl', // 'ssl' (465), 'starttls' (587) o 'none' (solo pruebas locales)
    'smtp_usuario' => getenv('FOLIO_SMTP_USUARIO') ?: '',
    'smtp_clave' => getenv('FOLIO_SMTP_CLAVE') ?: '',
    'smtp_remitente' => getenv('FOLIO_SMTP_REMITENTE') ?: '',
    'smtp_remitente_nombre' => 'Folio Facturación',
];

// Valores propios de cada equipo (claves) en config.local.php; ese archivo no va al ZIP.
return is_file(__DIR__ . '/config.local.php') ? array_replace($base, require __DIR__ . '/config.local.php') : $base;
