# Evaluación de cumplimiento · Taller DIAN SOAP PHP

Documento de referencia: `Taller-DIAN-SOAP-PHP-Guia-Explicativa.docx`.
Fecha de la revisión: 18 de noviembre de 2026. Todas las pruebas se ejecutaron contra el proyecto real con el servidor `php -S localhost:8080 router.php`.

**Resultado: el proyecto cumple todos los requisitos de la guía. No fue necesario modificar código.** Las únicas observaciones son del entorno y de la entrega (sección 5).

---

## 1. Entregable técnico (ZIP)

| # | Requisito de la guía | Evidencia en el proyecto | Estado |
|---|---|---|---|
| 1.1 | Nombre `Taller_SOAP_PHP_[Apellido]_[Nombre].zip` | `entregables/Taller_SOAP_PHP_Niño_Jaider.zip` | ✅ |
| 1.2 | Raíz: `config.php`, `router.php`, `README.md` con instrucciones de arranque | Los tres existen. El README explica arranque, pruebas y cURL/Postman | ✅ |
| 1.3 | `wsdl/FacturacionService.wsdl` completo y válido | Existe. DOMDocument devuelve `WSDL_SINTAXIS_VALIDA` | ✅ |
| 1.4 | `src/`: bootstrap, Repositorio, LectorFacturaXml, FacturacionService | Las 4 clases existen | ✅ |
| 1.5 | `public/`: `servicio.php` e `index.php` | Existen (más `folio.css` y `emision.js` de apoyo) | ✅ |
| 1.6 | `client/`: `cliente.php` con menú y `pruebas.php` | Existen (más `conexion.php`, helper compartido) | ✅ |
| 1.7 | `peticiones/`: `ConsultarRangos.xml`, `EnviarFactura.xml` | Existen y responden bien por cURL | ✅ |
| 1.8 | `data/facturas/` con al menos 2 `.json` generados en pruebas | 4 JSON (FACT121 y 3 SETP…) | ✅ |
| 1.9 | El ZIP coincide con el proyecto | Comparé el contenido extraído con la carpeta: idéntico (código, WSDL, JSON, README) | ✅ |

## 2. Entregable documental (PDF)

| # | Requisito | Evidencia | Estado |
|---|---|---|---|
| 2.1 | Nombre `Informe_SOAP_PHP_[Apellido1]_[Apellido1].pdf` | `Informe_SOAP_PHP_Niño_Niño.pdf` (la plantilla repite el apellido) | ✅ |
| 2.2 | Portada, objetivos, desarrollo paso a paso, conclusiones | PDF de 9 páginas, según `RUTA_DEL_PROYECTO.md` | ✅ |
| 2.3 | 6 evidencias con consola con fecha/hora/usuario | Declaradas E1–E6 en la ruta. No pude abrir las imágenes una por una | ⚠️ revisar a ojo |

## 3. Pasos 1 a 10 y puntos de control

| Paso | Qué pide la guía | Prueba ejecutada | Resultado | Estado |
|---|---|---|---|---|
| 1 | PHP 8.1+ con soap, dom, libxml, simplexml, openssl | PHP 8.2.12. `dom`, `libxml`, `SimpleXML`, `openssl` activos. `soap` solo con `-d extension=soap` | `ENTORNO_OK` solo con soap cargado | ⚠️ ver §5 |
| 2 | Estructura de 6 carpetas | `wsdl, src, public, client, data/facturas, peticiones` presentes | Correcto | ✅ |
| 3 | `config.php`, `bootstrap.php`, `Repositorio` sin BD | Caché WSDL desactivada, JSON en disco. `obtenerContribuyente('900123456')` | `Comercializadora Andina S.A.S.` | ✅ |
| 4 | WSDL con 3 operaciones, tipos XSD, mensajes, portType, binding, service | Revisado. Los nombres de operación coinciden con la guía y es document/literal | `WSDL_SINTAXIS_VALIDA` | ✅ |
| 5 | Lector XML con SimpleXML: emisor, duplicado, tolerancia 0,05, SHA-256 | Aprobación, duplicado y total inconsistente probados | Rechazos correctos | ✅ |
| 6 | Servicio con 3 métodos homónimos del WSDL | `ConsultarRangosNumeracion((object)['nit'=>'900123456'])` | `SERVICIO_OK: SETP` | ✅ |
| 7 | `servicio.php` (GET `?wsdl`, POST SOAP), `router.php`, `index.php` | WSDL: HTTP 200. Catálogo: HTTP 200. Muestra contribuyentes y facturas | Correcto | ✅ |
| 8 | Cliente SoapClient con menú (rangos, válida, error IVA, estado, salir) | Opción 1: RES-18760000001 / SETP. Opción 2: `APROBADA · 200` con SHA-256. Opción 3: `RECHAZADA · 422` | Correcto | ✅ |
| 9 | Suite con 6 pruebas | `php client/pruebas.php` | **6/6 pruebas exitosas (100 %)**, salida 0 | ✅ |
| 10 | Sobres SOAP por cURL/Postman | POST de `ConsultarRangos.xml` y `EnviarFactura.xml` con SOAPAction | Respuestas SOAP correctas (SETP, FACT120 APROBADA) | ✅ |

## 4. Lista de chequeo de la guía

| Autoevaluación | Cumple |
|---|---|
| Alineación al plan (SOAP, WSDL, XML en PHP, JSON, SoapClient/Postman) | ✅ |
| Sin bases de datos: arranca con `php -S localhost:8080 router.php` | ✅ |
| WSDL formal con operaciones, mensajes y tipos XSD | ✅ |
| Cada factura válida genera su `.json` en `data/facturas/` | ✅ |
| Interoperabilidad: responde a SoapClient y a cURL/Postman | ✅ |

---

## 5. Observaciones (no son fallos de código)

1. **Extensión `soap` desactivada en tu PHP por defecto.** `php -m` no la lista y el comando del Punto de Control 1 imprime `ERROR_FALTAN_EXTENSIONES`. El proyecto lo resuelve con `Iniciar_Folio.bat` o con `php -d extension=soap ...`, y está documentado en el README. Si repites la Evidencia 1, o activas `extension=soap` en `C:\xampp\php\php.ini` o ejecutas `php -d extension=soap -m`. Así la captura mostrará soap.
2. **Archivos de prueba que dejé en `data/facturas/`.** Al verificar, mis ejecuciones crearon 3 JSON extra: `FACT120.json`, `SETP991192105.json` y `SETP992749558.json`. Intenté borrarlos pero el borrado fue denegado, así que **no los eliminé**. Bórralos antes de entregar, para que `data/facturas/` coincida con el ZIP y con el PDF (4 facturas por $571.200,00). Si dejas FACT120, la petición `EnviarFactura.xml` saldrá `RECHAZADA` por duplicado, y la guía espera una aprobación.
3. **Mejoras sobre la guía (aceptables).** Se agregó `emision.js`, `folio.css` y el helper `conexion.php`. Se validan las fechas y los importes con aritmética en centavos y se bloquean los DTD. El NIT del sobre debe coincidir con el de la factura, y el registro usa un bloqueo para evitar duplicados concurrentes. El `README.md` es más extenso que el "breve" que pide la guía, pero no la contradice.
4. **Pendiente del estudiante.** Subir ZIP y PDF a ATENA, y comprobar que el PDF tenga tu nombre y los datos del integrante en la portada.

## 6. Cómo reproducir la verificación

```powershell
php -d extension=soap -S localhost:8080 router.php
php -d extension=soap client/pruebas.php
curl.exe -s -X POST http://localhost:8080/servicio.php -H "Content-Type: text/xml; charset=utf-8" -H "SOAPAction: \"http://uts.edu.co/facturacion/ConsultarRangosNumeracion\"" --data-binary "@peticiones/ConsultarRangos.xml"
```
