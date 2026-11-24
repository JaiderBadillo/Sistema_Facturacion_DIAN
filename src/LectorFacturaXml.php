<?php
declare(strict_types=1);

final class LectorFacturaXml
{
    private function centavos(string $importe, string $campo): int
    {
        if (!preg_match('/\A\d{1,12}(?:\.\d{1,2})?\z/', $importe)) {
            throw new DomainException("El campo {$campo} debe ser un importe positivo o cero con hasta dos decimales.");
        }
        [$entero, $decimal] = array_pad(explode('.', $importe), 2, '');
        return (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
    }

    public function procesar(string $xmlString, Repositorio $repo): array
    {
        if (strlen($xmlString) > 262144 || stripos($xmlString, '<!DOCTYPE') !== false || str_contains($xmlString, "\0")) {
            throw new InvalidArgumentException('La factura debe ser un XML UTF-8 sin DTD y de hasta 256 KiB.');
        }
        $anterior = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlString, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || $xml->getName() !== 'Factura') {
                throw new InvalidArgumentException('Se esperaba un XML válido con raíz Factura.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $numero = trim((string) $xml->Numero);
        $fecha = trim((string) $xml->Fecha);
        $nit = trim((string) $xml->Emisor->NIT);
        if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $numero) || $nit === '') {
            throw new InvalidArgumentException('Revise los campos Numero y Emisor/NIT.');
        }
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$dia || $dia->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException('La fecha debe ser válida y tener el formato AAAA-MM-DD.');
        }
        $correo = trim((string) $xml->Receptor->Correo);
        $enviarCorreo = strtolower(trim((string) $xml->EnvioCorreo)) === 'true';
        if ($correo !== '' && (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL))) {
            throw new DomainException('El correo del receptor no es válido.');
        }
        if ($enviarCorreo && $correo === '') {
            throw new DomainException('Para enviar la factura por correo se requiere el correo del receptor.');
        }
        $emisor = $repo->obtenerContribuyente($nit);
        if (!$emisor) {
            throw new DomainException("El emisor con NIT {$nit} no está registrado.");
        }
        if ($repo->existeFactura($numero)) {
            throw new DomainException("La factura {$numero} ya fue registrada.");
        }
        $subtotal = $this->centavos(trim((string) $xml->Totales->Subtotal), 'Subtotal');
        $iva = $this->centavos(trim((string) $xml->Totales->IVA), 'IVA');
        $total = $this->centavos(trim((string) $xml->Totales->Total), 'Total');
        if (abs($subtotal + $iva - $total) > 5) {
            throw new DomainException('El subtotal más el IVA no coincide con el total. La tolerancia es de 0,05 COP.');
        }
        $importe = sprintf('%d.%02d', intdiv($total, 100), $total % 100);
        return [
            'numero' => $numero, 'fecha' => $fecha, 'emisor_nit' => $nit,
            'emisor_nombre' => $emisor['razon_social'], 'receptor' => trim((string) $xml->Receptor->Nombre),
            'receptor_id' => trim((string) $xml->Receptor->Identificacion),
            'receptor_correo' => $correo, 'enviar_correo' => $enviarCorreo,
            'subtotal' => $subtotal / 100, 'iva' => $iva / 100, 'total' => $total / 100,
            'codigo_seguridad' => hash('sha256', implode('|', [$numero, $nit, $importe, $fecha])),
            'fecha_registro' => date('Y-m-d H:i:s'), 'estado' => 'APROBADA',
        ];
    }
}
