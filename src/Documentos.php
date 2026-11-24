<?php
declare(strict_types=1);

// Genera los documentos de una factura aprobada; los usan las descargas (factura.php) y el envío por correo.
final class Documentos
{
    public static function urlVerificacion(array $f, array $config): string
    {
        return rtrim($config['base_url'], '/') . '/verificar.php?cufe=' . $f['codigo_seguridad'];
    }

    public static function pdf(array $f, Repositorio $repo, array $config): string
    {
        return (new FacturaPdf())->generar($f, $repo->obtenerRangos($f['emisor_nit']), self::urlVerificacion($f, $config));
    }

    /** Contenedor XML; null si la factura se registró antes de conservar los XML. */
    public static function contenedor(array $f, Repositorio $repo, array $config): ?string
    {
        $original = $repo->obtenerXml($f['numero']);
        return $original === null ? null : (new FacturaContenedor())->generar($f, $original, self::urlVerificacion($f, $config));
    }
}
