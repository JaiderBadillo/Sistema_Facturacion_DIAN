<?php
// Copie este archivo como config.local.php y complete UNA de las dos opciones. config.local.php NO se entrega en el ZIP.
return [
    // Opción A: Brevo (API HTTPS)
    'brevo_api_key' => '',
    'brevo_remitente_email' => '',    // remitente verificado en Brevo
    'brevo_remitente_nombre' => 'Folio Facturación',

    // Opción B: SMTP (por ejemplo Gmail con contraseña de aplicación)
    'smtp_host' => '',                // smtp.gmail.com
    'smtp_puerto' => 465,
    'smtp_seguridad' => 'ssl',
    'smtp_usuario' => '',
    'smtp_clave' => '',
    'smtp_remitente' => '',
    'smtp_remitente_nombre' => 'Folio Facturación',
];
