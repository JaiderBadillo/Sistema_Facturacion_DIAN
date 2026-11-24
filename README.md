# Folio · Simulador de facturación electrónica

Taller de Web Services de las Unidades Tecnológicas de Santander. Una empresa envía una factura XML a un servicio SOAP 1.1 escrito en PHP. El servicio valida el documento y guarda cada aprobación como un archivo independiente en `data/facturas/`. El catálogo y el cliente de consola trabajan sobre el mismo registro local. Todos los NIT y las resoluciones son datos del laboratorio.

## 1. Iniciar el proyecto

**En Windows, extraiga el ZIP completo y haga doble clic en `Iniciar_Folio.bat`.** El iniciador localiza PHP en el PATH o en XAMPP, carga SOAP para esta ejecución y abre el catálogo en el navegador cuando el servicio responde. Mantenga abierta la ventana **Folio - Servidor PHP y SOAP** mientras trabaja. Para detener el servidor, presione Ctrl+C en esa ventana. Si vuelve a hacer doble clic mientras el servidor está activo, se abre el catálogo de la instancia existente.

**Arranque manual**

1. Extraiga el ZIP en una carpeta de su elección y abra allí una terminal.
2. Compruebe que PHP 8.1 o superior esté disponible con `php -v`. Ejecute `php -m` y verifique **soap, dom, libxml, SimpleXML y openssl**. La carpeta `data/facturas/` debe admitir escritura.
3. Desde la raíz, inicie el servidor y deje esa terminal abierta.

```powershell
php -S localhost:8080 router.php
```

4. Abra [el catálogo](http://localhost:8080/index.php) o [el contrato WSDL](http://localhost:8080/servicio.php?wsdl).
5. En una segunda terminal, abierta en la misma carpeta, ejecute el cliente o la suite.

```powershell
php client/cliente.php
php client/pruebas.php
```

**Opción para XAMPP si SOAP está desactivado.** Si `php -m` omite `soap` y la instalación incluye `ext/php_soap.dll`, use los siguientes comandos. El primero se ejecuta en la terminal del servidor y los otros dos en la del cliente. La opción `-d` carga SOAP únicamente para ese proceso. Puede localizar el archivo de configuración activo con `php --ini`.

```powershell
php -d extension=soap -S localhost:8080 router.php
php -d extension=soap client/cliente.php
php -d extension=soap client/pruebas.php
```

Para detener el servidor, presione Ctrl+C en su terminal. Si el puerto está ocupado, cierre la instancia anterior del servidor del taller. El puerto 8080 aparece en el contrato WSDL y en la configuración del cliente.

## 2. Probar el flujo

- **Navegador.** Seleccione **Nueva factura**, elija un emisor (hay cuatro de ejemplo y puede crear más) y el sistema propone su siguiente consecutivo. Elija uno de los clientes de ese emisor, o use **+ Agregar un cliente nuevo** (identificación y nombre) y vuelva a la factura con el cliente ya seleccionado. Complete fecha, subtotal e IVA (se calcula al 19 % y se puede editar). El navegador calcula el total y envía un sobre SOAP. Al aprobarse, se abre la ficha con los importes, la fecha de registro y la huella SHA-256. Si el número existe, el formulario muestra el rechazo.
- **Consola.** La opción 1 consulta la resolución del NIT 900123456. La 2 envía una factura de ejemplo por $119.000,00. La 3 induce un error de totales. La 4 consulta por número y NIT. La 5 cierra el cliente.
- **Suite.** Cada ejecución crea una factura válida por $238.000,00, intenta duplicarla, induce un error de cálculo y recupera el estado de la factura aprobada. El resultado esperado es `6/6 pruebas exitosas (100 %)` con código de salida 0. El registro aumenta en una factura por ejecución completa.

## 3. Enviar sobres manuales

En PowerShell, desde la raíz del proyecto:

```powershell
curl.exe -sS -i -X POST http://localhost:8080/servicio.php -H 'Content-Type: text/xml; charset=utf-8' -H 'SOAPAction: "http://uts.edu.co/facturacion/ConsultarRangosNumeracion"' --data-binary '@peticiones/ConsultarRangos.xml'
curl.exe -sS -i -X POST http://localhost:8080/servicio.php -H 'Content-Type: text/xml; charset=utf-8' -H 'SOAPAction: "http://uts.edu.co/facturacion/EnviarFactura"' --data-binary '@peticiones/EnviarFactura.xml'
```

El primer sobre consulta el rango SETP. El segundo registra FACT120 por $95.200,00 en el primer envío a una copia recién extraída. Al repetirlo, el campo `estado` devuelve `RECHAZADA` con código funcional 422 dentro del cuerpo SOAP. El estado HTTP de transporte puede seguir siendo 200.

En Postman, use **POST** sobre `http://localhost:8080/servicio.php`. Copie las dos cabeceras del comando correspondiente y pegue el XML en **Body → raw → XML**.

## 4. Archivos principales

| Ruta | Responsabilidad |
|---|---|
| `config.php`, `router.php` | Configuración local y enrutamiento del servidor integrado. |
| `wsdl/FacturacionService.wsdl` | Contrato WSDL 1.1 de las tres operaciones. |
| `src/` | Carga de clases, catálogo de contribuyentes, validación XML, lógica SOAP y persistencia JSON. |
| `public/` | Endpoint SOAP, catálogo, ficha y formulario de emisión web. |
| `client/` | Conexión SoapClient, menú de consola y pruebas de integración. |
| `peticiones/` | Sobres de consulta de rangos y de envío de factura. |
| `data/facturas/` | Un JSON por factura aprobada. Los ejemplos del paquete provienen de ejecuciones reales. |
| `config.local.php` | Lo crea usted (copie `config.local.example.php`) con su clave de Brevo. No va en el ZIP ni se comparte. |
| `n8n/` | Dos flujos de n8n (envío por correo y reintentos de contingencia) y su guía (`LEEME_N8N.md`). El correo lleva el PDF con código QR y el XML contenedor. Opcional: sin n8n todo lo demás funciona. |
| `data/emisores.json` | Se crea al registrar el primer emisor nuevo desde la web (**Emisores ▸ + Nuevo emisor**: NIT, razón social, prefijo, rango, resolución y vigencia). Se suma a los cuatro emisores de ejemplo. |
| `data/clientes/` | Se crea al agregar el primer cliente desde la web: un JSON por emisor. Cada emisor trae tres clientes de ejemplo en `Repositorio.php`. |
| `RUTA_DEL_PROYECTO.md` | Pasos numerados, requisitos, evidencias y estado del taller. |

## 5. Envío de la factura por correo

Si el cliente tiene correo y se marca **Enviar la factura (PDF y XML)**, Folio envía dos adjuntos: el PDF con código QR y el XML contenedor. Hay tres maneras de enviarlos; Folio usa la primera que esté configurada:

1. **Brevo directo (recomendado, no requiere instalar nada).** Es la misma API HTTPS que usa Vincula.
   1. En Brevo, tenga un remitente verificado y una clave API v3 (la de Vincula sirve; el plan gratuito permite 300 correos al día).
   2. Copie `config.local.example.php` como `config.local.php` y complete `brevo_api_key` y `brevo_remitente_email`. También puede usar las variables de entorno `FOLIO_BREVO_KEY` y `FOLIO_BREVO_REMITENTE`.
   3. No hace falta reiniciar el servidor. Facture de nuevo, o use **Reintentar envío ahora** en las facturas que quedaron en contingencia.
2. **SMTP directo (por ejemplo Gmail).** Complete en `config.local.php` `smtp_host`, `smtp_puerto`, `smtp_seguridad`, `smtp_usuario`, `smtp_clave` (contraseña de aplicación de Google) y `smtp_remitente`. Se usa si no hay clave de Brevo.
3. **n8n.** Si no hay ninguna de las dos, Folio avisa a n8n (ver `n8n/LEEME_N8N.md`).

Si el envío falla, la factura queda aprobada y en contingencia: se puede reintentar desde su ficha durante 48 horas.

## 6. Despliegue en Render

Ver **DESPLIEGUE_RENDER.md**: Docker, ender.yaml y la configuración de Brevo paso a paso.
