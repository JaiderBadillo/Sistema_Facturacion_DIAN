<?php
declare(strict_types=1);

final class Repositorio
{
    private const CONTRIBUYENTES = [
        '900123456' => ['nit' => '900123456', 'razon_social' => 'Comercializadora Andina S.A.S.', 'software_id' => 'SW-UTS-2026', 'estado' => 'ACTIVO'],
        '800987654' => ['nit' => '800987654', 'razon_social' => 'Industrias del Oriente Ltda.', 'software_id' => 'SW-UTS-8888', 'estado' => 'ACTIVO'],
        '901234567' => ['nit' => '901234567', 'razon_social' => 'Distribuidora Santander S.A.S.', 'software_id' => 'SW-UTS-3333', 'estado' => 'ACTIVO'],
        '890111222' => ['nit' => '890111222', 'razon_social' => 'Tecnologías del Nororiente S.A.', 'software_id' => 'SW-UTS-4444', 'estado' => 'ACTIVO'],
    ];

    // Clientes (receptores) de cada emisor. Los que se agregan desde la web se guardan en data/clientes/{nit}.json.
    private const CLIENTES_BASE = [
        '900123456' => [
            ['identificacion' => '1098765432', 'nombre' => 'Juan Pérez', 'correo' => 'juan.perez@example.com'],
            ['identificacion' => '900555111', 'nombre' => 'Librería del Centro', 'correo' => 'libreria.del.centro@example.com'],
            ['identificacion' => '900777333', 'nombre' => 'Taller La Estación', 'correo' => 'taller.la.estacion@example.com'],
        ],
        '800987654' => [
            ['identificacion' => '900000120', 'nombre' => 'Papelería del Parque', 'correo' => 'papeleria.del.parque@example.com'],
            ['identificacion' => '1100223344', 'nombre' => 'María Fernanda Ruiz', 'correo' => 'maria.fernanda.ruiz@example.com'],
            ['identificacion' => '901888999', 'nombre' => 'Ferretería El Tornillo', 'correo' => 'ferreteria.el.tornillo@example.com'],
        ],
        '901234567' => [
            ['identificacion' => '1005678901', 'nombre' => 'Andrés Camilo Rojas', 'correo' => 'andres.camilo.rojas@example.com'],
            ['identificacion' => '900321654', 'nombre' => 'Supermercado La Esquina', 'correo' => 'supermercado.la.esquina@example.com'],
            ['identificacion' => '1090456789', 'nombre' => 'Laura Sofía Mejía', 'correo' => 'laura.sofia.mejia@example.com'],
        ],
        '890111222' => [
            ['identificacion' => '900654987', 'nombre' => 'Colegio San Jorge', 'correo' => 'colegio.san.jorge@example.com'],
            ['identificacion' => '1101234567', 'nombre' => 'Diego Armando Pardo', 'correo' => 'diego.armando.pardo@example.com'],
            ['identificacion' => '901456123', 'nombre' => 'Clínica Santa Marta', 'correo' => 'clinica.santa.marta@example.com'],
        ],
    ];

    private const RANGOS = [
        '900123456' => ['prefijo' => 'SETP', 'desde' => 990000000, 'hasta' => 995000000, 'resolucion' => 'RES-18760000001', 'fecha_vigencia' => '2027-12-31'],
        '800987654' => ['prefijo' => 'FACT', 'desde' => 1, 'hasta' => 50000, 'resolucion' => 'RES-18760000002', 'fecha_vigencia' => '2027-06-30'],
        '901234567' => ['prefijo' => 'DSAN', 'desde' => 1000, 'hasta' => 99999, 'resolucion' => 'RES-18760000003', 'fecha_vigencia' => '2027-09-30'],
        '890111222' => ['prefijo' => 'TNOR', 'desde' => 5000, 'hasta' => 80000, 'resolucion' => 'RES-18760000004', 'fecha_vigencia' => '2028-03-31'],
    ];

    public function __construct(private string $dataDir)
    {
        if (!is_dir($dataDir) && !@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
            throw new RuntimeException('No se pudo abrir el directorio de facturas.');
        }
    }

    // Emisores: los cuatro de ejemplo (constantes) más los creados desde la web, guardados en data/emisores.json.
    private function archivoEmisores(): string
    {
        return dirname($this->dataDir) . '/emisores.json';
    }

    private function emisoresGuardados(): array
    {
        $archivo = $this->archivoEmisores();
        return is_file($archivo) ? json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    public function listarContribuyentes(): array
    {
        $nuevos = array_map(fn($e) => ['nit' => $e['nit'], 'razon_social' => $e['razon_social'], 'software_id' => 'SW-UTS-' . substr($e['nit'], -4), 'estado' => 'ACTIVO'], $this->emisoresGuardados());
        return [...array_values(self::CONTRIBUYENTES), ...$nuevos];
    }

    public function obtenerContribuyente(string $nit): ?array
    {
        foreach ($this->listarContribuyentes() as $c) {
            if ($c['nit'] === $nit) {
                return $c;
            }
        }
        return null;
    }

    public function obtenerRangos(string $nit): ?array
    {
        if (isset(self::RANGOS[$nit])) {
            return self::RANGOS[$nit];
        }
        foreach ($this->emisoresGuardados() as $e) {
            if ($e['nit'] === $nit) {
                return ['prefijo' => $e['prefijo'], 'desde' => (int) $e['desde'], 'hasta' => (int) $e['hasta'], 'resolucion' => $e['resolucion'], 'fecha_vigencia' => $e['fecha_vigencia']];
            }
        }
        return null;
    }

    public function agregarEmisor(string $nit, string $razonSocial, string $prefijo, string $desde, string $hasta, string $resolucion, string $vigencia): array
    {
        if (!preg_match('/\A\d{6,12}\z/', $nit)) {
            throw new DomainException('El NIT debe tener entre 6 y 12 dígitos, sin puntos ni guiones.');
        }
        if ($razonSocial === '' || mb_strlen($razonSocial) > 160) {
            throw new DomainException('Escribe la razón social (máximo 160 caracteres).');
        }
        if (!preg_match('/\A[A-Z]{1,6}\z/', $prefijo)) {
            throw new DomainException('El prefijo debe tener de 1 a 6 letras mayúsculas.');
        }
        if (!preg_match('/\A\d{1,12}\z/', $desde) || !preg_match('/\A\d{1,12}\z/', $hasta) || (int) $desde < 1 || (int) $hasta <= (int) $desde) {
            throw new DomainException('El rango debe ser numérico, empezar en 1 o más y terminar en un número mayor que el inicial.');
        }
        if (!preg_match('/\A[A-Za-z0-9-]{3,30}\z/', $resolucion)) {
            throw new DomainException('La resolución debe tener entre 3 y 30 letras, números o guiones.');
        }
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $vigencia);
        if (!$dia || $dia->format('Y-m-d') !== $vigencia || $dia < new DateTimeImmutable('today')) {
            throw new DomainException('La vigencia debe ser una fecha válida que no haya pasado.');
        }
        $bloqueo = fopen(dirname($this->archivoEmisores()) . '/.emisores.lock', 'c');
        if (!$bloqueo || !flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el registro de emisores.');
        }
        $temporal = null;
        try {
            foreach ($this->listarContribuyentes() as $c) {
                if ($c['nit'] === $nit) {
                    throw new DomainException("Ya existe un emisor con el NIT {$nit}.");
                }
                if ($this->obtenerRangos($c['nit'])['prefijo'] === $prefijo) {
                    throw new DomainException("El prefijo {$prefijo} ya lo usa «{$c['razon_social']}». Elige otro para que los números no se confundan.");
                }
            }
            $nuevo = ['nit' => $nit, 'razon_social' => $razonSocial, 'prefijo' => $prefijo, 'desde' => (int) $desde, 'hasta' => (int) $hasta, 'resolucion' => $resolucion, 'fecha_vigencia' => $vigencia];
            $json = json_encode([...$this->emisoresGuardados(), $nuevo], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
            $temporal = tempnam(dirname($this->archivoEmisores()), 'emisor-');
            if ($temporal === false || file_put_contents($temporal, $json) !== strlen($json) || !rename($temporal, $this->archivoEmisores())) {
                throw new RuntimeException('No fue posible guardar el emisor.');
            }
            return $this->obtenerContribuyente($nit);
        } finally {
            if (is_string($temporal) && is_file($temporal)) {
                unlink($temporal);
            }
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    private function archivoClientes(string $nit): string
    {
        return dirname($this->dataDir) . '/clientes/' . $nit . '.json';
    }

    private function clientesGuardados(string $nit): array
    {
        $archivo = $this->archivoClientes($nit);
        return is_file($archivo) ? json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    public function listarClientes(string $nit): array
    {
        if ($this->obtenerContribuyente($nit) === null) {
            return [];
        }
        return array_merge(self::CLIENTES_BASE[$nit] ?? [], $this->clientesGuardados($nit));
    }

    public function agregarCliente(string $nit, string $identificacion, string $nombre, string $correo = ''): array
    {
        if ($this->obtenerContribuyente($nit) === null) {
            throw new DomainException('El emisor no está registrado.');
        }
        if (!preg_match('/\A[0-9A-Za-z-]{5,20}\z/', $identificacion)) {
            throw new DomainException('La identificación debe tener entre 5 y 20 letras, números o guiones.');
        }
        if ($nombre === '' || mb_strlen($nombre) > 160) {
            throw new DomainException('Escribe el nombre o razón social (máximo 160 caracteres).');
        }
        if ($correo !== '' && (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL))) {
            throw new DomainException('El correo electrónico no es válido.');
        }
        $carpeta = dirname($this->archivoClientes($nit));
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo abrir el directorio de clientes.');
        }
        $bloqueo = fopen($carpeta . '/.clientes.lock', 'c');
        if (!$bloqueo || !flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el registro de clientes.');
        }
        $temporal = null;
        try {
            foreach ($this->listarClientes($nit) as $c) {
                if (strcasecmp($c['identificacion'], $identificacion) === 0) {
                    throw new DomainException("Ya existe un cliente con la identificación {$identificacion} para este emisor.");
                }
            }
            $nuevo = ['identificacion' => $identificacion, 'nombre' => $nombre, 'correo' => $correo];
            $json = json_encode([...$this->clientesGuardados($nit), $nuevo], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
            $temporal = tempnam($carpeta, 'cliente-');
            if ($temporal === false || file_put_contents($temporal, $json) !== strlen($json) || !rename($temporal, $this->archivoClientes($nit))) {
                throw new RuntimeException('No fue posible guardar el cliente.');
            }
            return $nuevo;
        } finally {
            if (is_string($temporal) && is_file($temporal)) {
                unlink($temporal);
            }
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    private function auxiliar(string $carpeta, string $numero, string $extension): ?string
    {
        return preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $numero) ? dirname($this->dataDir) . "/{$carpeta}/{$numero}.{$extension}" : null;
    }

    private function escribirAuxiliar(?string $ruta, string $contenido): void
    {
        if ($ruta === null) {
            throw new DomainException('Número de factura inválido.');
        }
        $carpeta = dirname($ruta);
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo abrir el directorio auxiliar.');
        }
        if (file_put_contents($ruta, $contenido, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo.');
        }
    }

    // El XML original se conserva en data/xml/ para poder adjuntarlo al correo.
    public function guardarXml(string $numero, string $xml): void
    {
        $this->escribirAuxiliar($this->auxiliar('xml', $numero, 'xml'), $xml);
    }

    public function obtenerXml(string $numero): ?string
    {
        $ruta = $this->auxiliar('xml', $numero, 'xml');
        return $ruta !== null && is_file($ruta) ? file_get_contents($ruta) : null;
    }

    // Estado del envío por correo de cada factura en data/correos/{numero}.json (se fusiona con el anterior).
    public function guardarEstadoCorreo(string $numero, array $estado): void
    {
        $this->escribirAuxiliar($this->auxiliar('correos', $numero, 'json'),
            json_encode([...($this->obtenerEstadoCorreo($numero) ?? []), ...$estado], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
    }

    /** @return array<string, array> estado del envío por correo de cada factura, indexado por número */
    public function listarEstadosCorreo(): array
    {
        $estados = [];
        foreach (glob(dirname($this->dataDir) . '/correos/*.json') ?: [] as $ruta) {
            $estados[basename($ruta, '.json')] = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        }
        return $estados;
    }

    public function obtenerEstadoCorreo(string $numero): ?array
    {
        $ruta = $this->auxiliar('correos', $numero, 'json');
        return $ruta !== null && is_file($ruta) ? json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR) : null;
    }

    private function ruta(string $numero): ?string
    {
        return preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $numero) ? $this->dataDir . '/' . $numero . '.json' : null;
    }

    public function existeFactura(string $numero): bool
    {
        $ruta = $this->ruta($numero);
        return $ruta !== null && is_file($ruta);
    }

    public function guardarFactura(string $numero, array $factura): bool
    {
        $ruta = $this->ruta($numero);
        if ($ruta === null) {
            throw new DomainException('Número de factura inválido.');
        }
        $json = json_encode($factura, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        $bloqueo = fopen($this->dataDir . '/.registro.lock', 'c');
        if (!$bloqueo || !flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el registro.');
        }
        $temporal = null;
        try {
            clearstatcache(true, $ruta);
            if (is_file($ruta)) {
                throw new DomainException("La factura {$numero} ya fue registrada.");
            }
            $temporal = tempnam($this->dataDir, 'registro-');
            return $temporal !== false && file_put_contents($temporal, $json) === strlen($json) && rename($temporal, $ruta);
        } finally {
            if (is_string($temporal) && is_file($temporal)) {
                unlink($temporal);
            }
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function obtenerFactura(string $numero): ?array
    {
        $ruta = $this->ruta($numero);
        if ($ruta === null || !is_file($ruta)) {
            return null;
        }
        return json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    public function listarFacturas(): array
    {
        $facturas = [];
        foreach (glob($this->dataDir . '/*.json') ?: [] as $ruta) {
            $facturas[] = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        }
        usort($facturas, static fn($a, $b) => [$b['fecha_registro'], $b['numero']] <=> [$a['fecha_registro'], $a['numero']]);
        return $facturas;
    }
}
