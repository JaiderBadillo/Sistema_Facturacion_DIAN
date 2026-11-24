<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';
spl_autoload_register(static function (string $clase): void {
    if (in_array($clase, ['Repositorio', 'LectorFacturaXml', 'FacturacionService', 'NotificadorN8n', 'FacturaPdf', 'CodigoQr', 'FacturaContenedor', 'EnvioCorreo', 'Documentos', 'NotificadorBrevo', 'NotificadorSmtp', 'CorreoFactura'], true)) {
        require_once __DIR__ . '/' . $clase . '.php';
    }
});
