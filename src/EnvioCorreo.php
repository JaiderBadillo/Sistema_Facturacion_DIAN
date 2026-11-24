<?php
declare(strict_types=1);

// Envío de la factura por correo (vía n8n) con contingencia: si la entrega falla, la factura sigue aprobada,
// queda en estado CONTINGENCIA y se reintenta hasta cumplir el plazo (48 h, como la contingencia de la DIAN).
final class EnvioCorreo
{
    public const PLAZO_HORAS = 48;
    private const ESPERA_ENTRE_INTENTOS = 60; // segundos, para los reintentos automáticos

    /** Envía por Brevo o por SMTP (el primero configurado) o pide el envío a n8n, y registra el estado. @return array{ok: bool, detalle: string, modo: string} */
    public static function solicitar(Repositorio $repo, array $f, array $config): array
    {
        $previo = $repo->obtenerEstadoCorreo($f['numero']) ?? [];
        $repo->guardarEstadoCorreo($f['numero'], [
            'estado' => 'EN_COLA', 'destino' => $f['receptor_correo'], 'fecha' => date('Y-m-d H:i:s'),
            'intentos' => ($previo['intentos'] ?? 0) + 1,
            'plazo' => $previo['plazo'] ?? date('Y-m-d H:i:s', strtotime($f['fecha_registro'] . ' +' . self::PLAZO_HORAS . ' hours')),
            'detalle' => '',
        ]);
        $modo = NotificadorBrevo::configurado($config) ? 'brevo' : (NotificadorSmtp::configurado($config) ? 'smtp' : 'n8n');
        try {
            if ($modo === 'n8n') {
                $r = (new NotificadorN8n($config))->enviar($f);
            } else {
                $contenedor = Documentos::contenedor($f, $repo, $config) ?? throw new RuntimeException('Falta el XML de la factura.');
                $envio = $modo === 'brevo' ? new NotificadorBrevo($config) : new NotificadorSmtp($config);
                $r = $envio->enviar($f, Documentos::pdf($f, $repo, $config), $contenedor);
            }
        } catch (Throwable $e) {
            $r = ['ok' => false, 'detalle' => $e->getMessage()];
        }
        if (!$r['ok']) {
            $repo->guardarEstadoCorreo($f['numero'], ['estado' => 'CONTINGENCIA', 'detalle' => $r['detalle']]);
        } elseif ($modo !== 'n8n') {
            // Brevo y SMTP confirman al instante; con n8n el estado lo informa el flujo al terminar.
            $repo->guardarEstadoCorreo($f['numero'], ['estado' => 'ENVIADO', 'fecha' => date('Y-m-d H:i:s'), 'detalle' => $r['detalle']]);
        }
        return $r + ['modo' => $modo];
    }

    /**
     * Reintenta las facturas en contingencia. Con $solo reintenta únicamente esa factura (botón manual).
     * @return array{reintentados: int, vencidas: int}
     */
    public static function reintentarPendientes(Repositorio $repo, array $config, ?string $solo = null): array
    {
        $resultado = ['reintentados' => 0, 'vencidas' => 0];
        foreach ($repo->listarEstadosCorreo() as $numero => $estado) {
            if (($estado['estado'] ?? '') !== 'CONTINGENCIA' || ($solo !== null && $solo !== $numero)) {
                continue;
            }
            if (time() > strtotime($estado['plazo'])) {
                $repo->guardarEstadoCorreo($numero, ['estado' => 'VENCIDA', 'fecha' => date('Y-m-d H:i:s')]);
                $resultado['vencidas']++;
                continue;
            }
            if ($solo === null && time() - strtotime($estado['fecha']) < self::ESPERA_ENTRE_INTENTOS) {
                continue;
            }
            $factura = $repo->obtenerFactura($numero);
            if ($factura) {
                self::solicitar($repo, $factura, $config);
                $resultado['reintentados']++;
            }
        }
        return $resultado;
    }
}
