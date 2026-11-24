<?php
declare(strict_types=1);

final class FacturacionService
{
    private Repositorio $repo;
    private LectorFacturaXml $lector;

    public function __construct(?Repositorio $repo = null, ?LectorFacturaXml $lector = null)
    {
        global $config;
        $this->repo = $repo ?? new Repositorio($config['data_dir']);
        $this->lector = $lector ?? new LectorFacturaXml();
    }

    private function parametro(object|array $peticion, string $campo): string
    {
        $valor = trim((string) (((array) $peticion)[$campo] ?? ''));
        if ($valor === '') {
            throw new SoapFault('Client', "El parámetro {$campo} es obligatorio.");
        }
        return $valor;
    }

    public function ConsultarRangosNumeracion(object|array $peticion): array
    {
        $nit = $this->parametro($peticion, 'nit');
        $rango = $this->repo->obtenerRangos($nit);
        return [
            'valido' => $rango !== null,
            'mensaje' => $rango ? 'Resolución de numeración autorizada activa.' : "No existen resoluciones para el NIT {$nit}.",
            'prefijo' => $rango['prefijo'] ?? '', 'desde' => $rango['desde'] ?? 0, 'hasta' => $rango['hasta'] ?? 0,
            'resolucion' => $rango['resolucion'] ?? '', 'fechaVigencia' => $rango['fecha_vigencia'] ?? '',
        ];
    }

    public function EnviarFactura(object|array $peticion): array
    {
        $nit = $this->parametro($peticion, 'nitEmisor');
        $xml = $this->parametro($peticion, 'facturaXml');
        try {
            $factura = $this->lector->procesar($xml, $this->repo);
            if ($factura['emisor_nit'] !== $nit) {
                throw new DomainException('El NIT del sobre SOAP no coincide con el emisor de la factura.');
            }
            if (!$this->repo->guardarFactura($factura['numero'], $factura)) {
                throw new RuntimeException('Error al escribir la factura.');
            }
            $this->repo->guardarXml($factura['numero'], $xml);
            $correo = $factura['enviar_correo'] ? $this->solicitarCorreo($factura) : '';
            return [
                'estado' => 'APROBADA', 'codigo' => '200', 'mensaje' => "Factura {$factura['numero']} validada y registrada.{$correo}",
                'codigoSeguridad' => $factura['codigo_seguridad'], 'fechaValidacion' => $factura['fecha_registro'],
            ];
        } catch (DomainException $e) {
            return ['estado' => 'RECHAZADA', 'codigo' => '422', 'mensaje' => $e->getMessage(),
                'codigoSeguridad' => '', 'fechaValidacion' => date('Y-m-d H:i:s')];
        } catch (InvalidArgumentException $e) {
            throw new SoapFault('Client', $e->getMessage());
        } catch (Throwable $e) {
            error_log($e->getMessage());
            throw new SoapFault('Server', 'No fue posible registrar la factura.');
        }
    }

    // Un fallo del correo nunca tumba la aprobación: la factura queda en contingencia y se reintenta (ver EnvioCorreo).
    private function solicitarCorreo(array $f): string
    {
        global $config;
        $r = EnvioCorreo::solicitar($this->repo, $f, $config);
        return $r['ok']
            ? ($r['modo'] !== 'n8n' ? " Correo enviado a {$f['receptor_correo']}." : " Correo en cola para {$f['receptor_correo']}.")
            : ' La factura quedó registrada; el correo quedó en contingencia y puede reintentarse desde la ficha de la factura.';
    }

    public function ConsultarEstadoFactura(object|array $peticion): array
    {
        $numero = $this->parametro($peticion, 'numeroFactura');
        $nit = $this->parametro($peticion, 'nitEmisor');
        $factura = $this->repo->obtenerFactura($numero);
        $existe = $factura !== null && $factura['emisor_nit'] === $nit;
        return [
            'existe' => $existe, 'estado' => $existe ? $factura['estado'] : 'NO_ENCONTRADA',
            'numeroFactura' => $numero, 'totalFactura' => $existe ? $factura['total'] : 0.0,
            'fechaRegistro' => $existe ? $factura['fecha_registro'] : '',
        ];
    }
}
