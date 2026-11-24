# Guía para exponer el proyecto · Folio (Facturación electrónica con SOAP en PHP)

Esta guía explica el proyecto desde cero. Léela en orden. Al final hay un guion de exposición de 8 minutos y preguntas que el profesor podría hacer.

---

## 1. ¿De qué trata el proyecto, en una frase?

Es un **servicio web** que recibe **facturas electrónicas en XML**, las **valida** y las **guarda como archivos JSON**. Un **cliente** (programa de consola, navegador o cURL) le envía las facturas por internet usando el protocolo **SOAP**.

Es una simulación académica de cómo una empresa le envía una factura a una entidad validadora (en Colombia, la DIAN). No se conecta a la DIAN real. Los NIT y las resoluciones son inventados.

### Analogía para entenderlo
Piensa en un **restaurante**:

| Restaurante | Proyecto |
|---|---|
| El **menú** (lo que puedes pedir y cómo) | El **WSDL** (contrato) |
| El **mesero** que lleva pedidos | El protocolo **SOAP** (sobre XML) |
| La **cocina** (hace el trabajo) | El **servidor PHP** (`FacturacionService`) |
| El **cliente** que pide | `cliente.php`, navegador, cURL |
| El **archivador** donde se guardan las comandas | La carpeta `data/facturas/` con JSON |

El cliente no necesita saber cómo funciona la cocina. Solo lee el menú y pide. Eso se llama **bajo acoplamiento** y es la idea central de **SOA** (Arquitectura Orientada a Servicios).

---

## 2. Conceptos que debes dominar (en orden)

### 2.1 Servicio web
Un programa que otro programa puede usar a través de la red (HTTP). Aquí el servidor escucha en `http://localhost:8080`.

### 2.2 XML
Formato de texto con etiquetas para describir datos. Ejemplo de la factura:

```xml
<Factura>
  <Numero>SETP990000001</Numero>
  <Fecha>2026-11-11</Fecha>
  <Emisor><NIT>900123456</NIT></Emisor>
  <Totales><Subtotal>100000.00</Subtotal><IVA>19000.00</IVA><Total>119000.00</Total></Totales>
</Factura>
```

### 2.3 JSON
Otro formato de datos, más corto. Aquí se usa como **"base de datos casera"**: cada factura aprobada se guarda en un archivo, por ejemplo `data/facturas/FACT121.json`. **No hay MySQL ni ninguna base de datos.**

### 2.4 SOAP
Protocolo de mensajes **basado en XML**. Cada mensaje viaja dentro de un "sobre" (Envelope):

| Elemento | ¿Obligatorio? | Para qué sirve |
|---|---|---|
| `Envelope` | Sí | Raíz del mensaje |
| `Header` | No | Metadatos (tokens, etc.) |
| `Body` | Sí | La operación y sus parámetros, o la respuesta |
| `Fault` | Solo en error | Código y descripción del error |

### 2.5 WSDL
Es el **contrato** del servicio, escrito en XML. Dice:
- `types`: qué datos viajan (con XSD: `xsd:string`, `xsd:long`, etc.).
- `message`: los paquetes de entrada y salida.
- `portType`: las operaciones disponibles (como una interfaz).
- `binding` y `service`: el protocolo (SOAP sobre HTTP) y la dirección URL.

Un cliente puede **leer el WSDL y saber solo** cómo llamar al servicio.

### 2.6 Clases de PHP que se usan
- `SoapServer`: recibe sobres SOAP y llama a tu clase PHP.
- `SoapClient`: lee el WSDL y te deja llamar al servicio como si fuera una función local.
- `SimpleXMLElement`: lee un XML con una sintaxis fácil (`$xml->Emisor->NIT`).

---

## 3. Las tres operaciones del servicio

| Operación | Entrada | Qué hace | Salida |
|---|---|---|---|
| `ConsultarRangosNumeracion` | `nit` | Devuelve el prefijo y rango de numeración autorizado a ese NIT | `valido`, `prefijo`, `desde`, `hasta`, `resolucion`, `fechaVigencia` |
| `EnviarFactura` | `nitEmisor`, `facturaXml` | Valida la factura y, si es correcta, la guarda | `estado` (APROBADA/RECHAZADA), `codigo` (200/422), `mensaje`, `codigoSeguridad`, `fechaValidacion` |
| `ConsultarEstadoFactura` | `numeroFactura`, `nitEmisor` | Busca una factura ya guardada | `existe`, `estado`, `totalFactura`, `fechaRegistro` |

### Datos de ejemplo (en memoria)
| NIT | Empresa | Prefijo | Rango | Resolución |
|---|---|---|---|---|
| 900123456 | Comercializadora Andina S.A.S. | SETP | 990000000 – 995000000 | RES-18760000001 |
| 800987654 | Industrias del Oriente Ltda. | FACT | 1 – 50000 | RES-18760000002 |

---

## 4. Estructura de carpetas y para qué sirve cada archivo

```
Facturacion_electronica_DIAN/
├── config.php            Configuración (URLs, rutas, caché WSDL apagada)
├── router.php            Decide qué archivo atiende cada URL
├── README.md             Instrucciones de arranque
├── Iniciar_Folio.bat     Doble clic para arrancar en Windows
├── wsdl/
│   └── FacturacionService.wsdl     El contrato
├── src/                  La lógica ("la cocina")
│   ├── bootstrap.php               Carga config y las clases
│   ├── Repositorio.php             Datos en memoria + guardar/leer JSON
│   ├── LectorFacturaXml.php        Lee y valida el XML de la factura
│   └── FacturacionService.php      Las 3 operaciones SOAP
├── public/               Lo que se ve desde HTTP
│   ├── servicio.php                Endpoint SOAP (y entrega el WSDL)
│   ├── index.php                   Catálogo web con las facturas
│   ├── folio.css / emision.js      Diseño y formulario web
├── client/               Los clientes
│   ├── conexion.php                Crea el SoapClient (lo comparten los otros)
│   ├── cliente.php                 Menú interactivo de consola
│   └── pruebas.php                 6 pruebas automáticas
├── peticiones/           Sobres SOAP escritos a mano para cURL/Postman
│   ├── ConsultarRangos.xml
│   └── EnviarFactura.xml
├── data/facturas/        Un .json por cada factura aprobada
└── entregables/          El ZIP y el PDF de la entrega
```

**Regla de oro para explicar:** `wsdl/` es el contrato, `src/` es la lógica, `public/` es la puerta de entrada web, `client/` son los que consumen, `data/` es donde se guarda.

---

## 5. Cómo se construyó, paso a paso (el mismo orden de la guía)

### Paso 1 · Entorno
PHP 8.1+ con las extensiones `soap`, `dom`, `libxml`, `SimpleXML` y `openssl`.
- `soap` da `SoapServer` y `SoapClient`.
- `openssl` / `hash` permite calcular el SHA-256.
- En este equipo `soap` viene apagada. Se enciende con `php -d extension=soap ...` o con `Iniciar_Folio.bat`.

### Paso 2 · Estructura de carpetas
Seis carpetas con responsabilidades separadas (ver sección 4).

### Paso 3 · Configuración y repositorio (sin base de datos)
- **`config.php`** apaga la caché del WSDL (`soap.wsdl_cache_enabled = 0`). Si no, PHP seguiría usando una versión vieja del contrato al editarlo. También define la URL (`localhost:8080`) y la carpeta de datos.
- **`bootstrap.php`** carga la configuración y registra un *autoloader*: cuando el código usa `new Repositorio()`, PHP busca y carga `src/Repositorio.php` solo.
- **`Repositorio.php`** tiene:
  - Los dos contribuyentes y sus rangos como **constantes en memoria** (reemplazan a las tablas SQL).
  - `guardarFactura()`: convierte la factura a JSON y la guarda en `data/facturas/{numero}.json`.
  - `obtenerFactura()`, `existeFactura()`, `listarFacturas()`.
  - **Seguridad:** el número de factura solo puede tener letras, números, guion y guion bajo (`/\A[A-Za-z0-9_-]{1,64}\z/`). Así nadie puede enviar `../../algo` para escribir fuera de la carpeta.
  - **Escritura segura:** usa un bloqueo (`flock`) y escribe primero a un archivo temporal que luego renombra. Así, dos envíos simultáneos con el mismo número no crean duplicados y un archivo nunca queda a medias.

### Paso 4 · El contrato WSDL
Se escribió `FacturacionService.wsdl` con:
1. **types**: 6 elementos (una solicitud y una respuesta por cada operación).
2. **message**: 6 mensajes que apuntan a esos elementos.
3. **portType**: las 3 operaciones.
4. **binding**: estilo `document`, uso `literal`, con su `soapAction`.
5. **service**: la dirección `http://localhost:8080/servicio.php`.

### Paso 5 · El lector de facturas (`LectorFacturaXml.php`)
Recibe el texto XML y hace estas validaciones **en este orden**:

1. **Seguridad XML:** máximo 256 KiB, sin `<!DOCTYPE` y con `LIBXML_NONET`. Esto bloquea el ataque **XXE** (un XML malicioso que intenta leer archivos del servidor).
2. **Estructura:** que sea XML válido y la raíz sea `<Factura>`.
3. **Número y NIT:** formato permitido y NIT no vacío.
4. **Fecha:** debe existir de verdad y tener formato `AAAA-MM-DD`.
5. **Emisor:** el NIT debe estar registrado en el repositorio.
6. **Duplicado:** el número no puede haberse guardado antes.
7. **Importes:** Subtotal, IVA y Total deben ser números con máximo 2 decimales. Se convierten a **centavos enteros** para no tener errores de decimales de punto flotante.
8. **Aritmética:** `Subtotal + IVA` debe ser igual al `Total` con tolerancia de **0,05 COP** (5 centavos).
9. **Código de seguridad:** SHA-256 de `numero|nit|total|fecha`. Sirve como **huella** de trazabilidad: si alguien cambia un dato, la huella ya no coincide.

Si todo está bien, devuelve los datos limpios con `estado = APROBADA`.

### Paso 6 · El servicio (`FacturacionService.php`)
Una clase con **tres métodos que se llaman igual que las operaciones del WSDL** (esto es obligatorio, así `SoapServer` los asocia).

**Diferencia clave que debes saber explicar:**
- **Rechazo de negocio** (`DomainException`): total mal calculado, factura repetida, emisor desconocido. Se responde **normal** con `estado = RECHAZADA` y `codigo = 422`. La llamada SOAP tuvo éxito, la factura no.
- **Error de protocolo** (`SoapFault`): faltan parámetros, XML mal formado. Se devuelve un **Fault**, el error estándar de SOAP.

Además, `EnviarFactura` verifica que el `nitEmisor` del sobre coincida con el NIT dentro de la factura.

### Paso 7 · Publicar el servidor
- **`servicio.php`**:
  - Si llega un **GET con `?wsdl`** → entrega el contrato.
  - Si llega un **POST** → crea `new SoapServer(wsdl)`, le asigna la clase con `setClass(FacturacionService::class)` y llama `handle()`, que lee el sobre, ejecuta el método y responde con otro sobre.
- **`router.php`**: el servidor integrado de PHP (`php -S`) lo usa para decidir qué archivo responde cada ruta (`/` → catálogo, `/servicio.php` → SOAP).
- **`index.php`**: página web que lee las facturas JSON y muestra el total facturado, la cantidad y los emisores autorizados.
- Se arranca con: `php -S localhost:8080 router.php`

### Paso 8 · Cliente de consola (`cliente.php`)
Crea un `SoapClient` con la URL del WSDL. Gracias al WSDL puede llamar `$cliente->ConsultarRangosNumeracion(['nit' => '900123456'])` como si fuera una función local. Menú:
1. Consultar rangos · 2. Enviar factura válida · 3. Enviar factura con error de IVA · 4. Consultar estado · 5. Salir.

### Paso 9 · Pruebas automáticas (`pruebas.php`)
Seis pruebas contra el servidor real:

| # | Prueba | Debe pasar si… |
|---|---|---|
| 1 | Rango para NIT 900123456 | `valido` y prefijo SETP |
| 2 | NIT sin resolución (111222333) | `valido = false` |
| 3 | Enviar factura válida | APROBADA y el SHA-256 coincide con el calculado |
| 4 | Reenviar la misma factura | RECHAZADA 422 por duplicado |
| 5 | Total que no cuadra | RECHAZADA |
| 6 | Consultar la factura aprobada | existe, APROBADA, total 238.000 |

Resultado esperado: **6/6 (100 %)**.

### Paso 10 · Interoperabilidad (cURL / Postman)
Se demuestra que el servicio **no depende de PHP**: se envía un sobre SOAP escrito a mano (`peticiones/ConsultarRangos.xml`) con cURL, indicando `Content-Type: text/xml` y `SOAPAction`. El servidor responde con el sobre de respuesta. Esa es la prueba de que SOAP es un estándar independiente del lenguaje.

---

## 6. ¿Qué pasa por dentro cuando se envía una factura? (flujo completo)

```
cliente.php
  │ 1. Lee el WSDL (GET /servicio.php?wsdl) y aprende las operaciones
  │ 2. Arma el XML de la factura
  │ 3. Llama EnviarFactura(...)  → SoapClient crea el sobre SOAP
  ▼
HTTP POST  →  router.php  →  public/servicio.php
  │ 4. SoapServer lee el sobre y llama FacturacionService::EnviarFactura
  ▼
FacturacionService
  │ 5. LectorFacturaXml valida (secciones 5.5)
  │ 6. Repositorio guarda data/facturas/NUMERO.json
  ▼
Respuesta SOAP: estado=APROBADA, codigo=200, codigoSeguridad=<sha256>
  ▼
cliente.php imprime el resultado
```

---

## 7. Cómo ejecutarlo en la exposición (demo)

**Terminal 1 (servidor)**
```powershell
php -d extension=soap -S localhost:8080 router.php
```
(O doble clic en `Iniciar_Folio.bat`.) **Déjala abierta.**

**Terminal 2 (cliente)**
```powershell
php -d extension=soap client/cliente.php
```
Elige 1 (rangos), 2 (factura válida → APROBADA), 3 (error de IVA → RECHAZADA 422) y 5 (salir).

```powershell
php -d extension=soap client/pruebas.php
```
Muestra 6/6.

**Navegador:** abre `http://localhost:8080/servicio.php?wsdl` (el contrato) y `http://localhost:8080/index.php` (el catálogo con las facturas guardadas).

**cURL (interoperabilidad)**
```powershell
curl.exe -s -X POST http://localhost:8080/servicio.php -H "Content-Type: text/xml; charset=utf-8" -H "SOAPAction: \"http://uts.edu.co/facturacion/ConsultarRangosNumeracion\"" --data-binary "@peticiones/ConsultarRangos.xml"
```

> Si algo falla en vivo: casi siempre es que **el servidor no está corriendo** o que `soap` no está activa. Revisa esas dos cosas primero.

---

## 8. Guion sugerido (8 minutos)

1. **(1 min) Problema:** "Las empresas deben enviar sus facturas electrónicas a una entidad que las valide. Simulé ese intercambio con un servicio web."
2. **(1 min) Conceptos:** SOA = servicios independientes. SOAP = mensajes XML. WSDL = contrato. Usa la analogía del restaurante.
3. **(1 min) Arquitectura:** muestra el árbol de carpetas (sección 4).
4. **(1 min) WSDL:** muestra `?wsdl` en el navegador y señala las 3 operaciones.
5. **(2 min) Demo:** cliente → opciones 1, 2 y 3. Luego abre el catálogo y muestra la factura guardada y su JSON en `data/facturas/`.
6. **(1 min) Pruebas e interoperabilidad:** `pruebas.php` 6/6 y la petición con cURL.
7. **(1 min) Conclusiones:** el contrato separa cliente y servidor; los JSON reemplazan la base de datos; se distingue rechazo de negocio (422) de error de protocolo (Fault); se validó seguridad (XXE, rutas).

---

## 9. Preguntas probables y cómo responderlas

**¿Por qué SOAP y no REST?**
SOAP tiene un contrato formal (WSDL) que genera clientes automáticamente y es el estándar usado en facturación electrónica real (la DIAN usa SOAP). REST es más liviano pero no exige contrato.

**¿Para qué sirve el WSDL?**
Describe operaciones, parámetros y la URL. El cliente lo lee y sabe cómo llamar sin conocer el servidor.

**¿Dónde está la base de datos?**
No hay. Los datos fijos (contribuyentes y rangos) están en memoria, en constantes de `Repositorio.php`, y cada factura aprobada se guarda como un archivo JSON.

**¿Qué es el código de seguridad?**
Un hash SHA-256 de `numero|nit|total|fecha`. Es una huella: si alguien modifica la factura, el hash cambia.

**¿Qué pasa si envío la misma factura dos veces?**
La segunda devuelve `RECHAZADA` con código 422 y el mensaje "ya fue registrada". Además el guardado usa bloqueo para evitar duplicados por envíos simultáneos.

**¿Qué diferencia hay entre RECHAZADA y un SoapFault?**
RECHAZADA es una respuesta normal: la llamada funcionó pero la factura no cumple reglas. Un SoapFault es un error técnico del mensaje (parámetro faltante, XML roto).

**¿Por qué se desactiva la caché del WSDL?**
Porque PHP guarda el WSDL en memoria y, en desarrollo, seguiría usando la versión anterior después de editarlo.

**¿Qué es XXE y cómo se evitó?**
Es un ataque en el que un XML trae una entidad externa para que el servidor lea archivos o haga peticiones. Se evita rechazando `<!DOCTYPE` y usando `LIBXML_NONET`.

**¿Por qué los importes se convierten a centavos?**
Para evitar errores de decimales con `float` (0,1 + 0,2 ≠ 0,3). Con enteros la suma es exacta.

**¿Por qué la tolerancia de 0,05?**
Permite diferencias de redondeo de hasta 5 centavos entre Subtotal + IVA y Total.

**¿Cómo demuestran que es interoperable?**
Con cURL: se envía el sobre SOAP sin PHP y el servidor responde igual que al cliente PHP.

**¿Qué es `document/literal`?**
Es el estilo del binding: el cuerpo del mensaje es un documento XML definido por el esquema (`literal`), no una llamada codificada tipo RPC.

---

## 10. Glosario rápido

| Término | Significado |
|---|---|
| **SOA** | Arquitectura de servicios independientes que se comunican por contratos |
| **SOAP** | Protocolo de mensajes XML |
| **WSDL** | Contrato XML del servicio |
| **XSD** | Esquema que define los tipos de datos del XML |
| **Endpoint** | URL donde responde el servicio (`/servicio.php`) |
| **SoapServer / SoapClient** | Clases de PHP para servidor y cliente SOAP |
| **NIT** | Número de identificación tributaria de la empresa |
| **Resolución de numeración** | Autorización oficial de un rango de números de factura |
| **SHA-256** | Función hash que genera una huella única de 64 caracteres |
| **XXE** | Ataque por entidades externas en XML |
| **Persistencia JSON** | Guardar datos en archivos `.json` en lugar de una base de datos |
