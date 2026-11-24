# Ruta de trabajo · Simulador de facturación electrónica

**Curso:** Web Services · UTS · Corte III  
**Objetivo:** recibir una factura XML por SOAP, validar su contenido, guardar la aprobación en JSON y consultarla desde otro cliente.  
**Alcance:** laboratorio local en PHP. Todas las operaciones usan contribuyentes de ejemplo.

## 1. Punto de reanudación

Al retomar el proyecto existían el contrato, las clases del servidor, el cliente, la suite y los dos sobres XML. El endpoint publicaba el WSDL con HTTP 200. Faltaban el catálogo web, el README, las facturas generadas mediante pruebas, el informe y el paquete de entrega. XAMPP tenía PHP 8.2.12 y la extensión SOAP disponible para cargar por proceso.

## 2. Secuencia de implementación y comprobación

| Paso | Tarea y requisito de aceptación | Comprobación | Estado de cierre |
|---|---|---|---|
| 01 | Preparar PHP 8.1 o superior con soap, dom, libxml, SimpleXML y openssl. | Consultar versión y módulos en la misma configuración que usará el servidor. | Verificado · E1 |
| 02 | Organizar raíz, wsdl, src, public, client, peticiones y data/facturas. | Revisar el árbol del proyecto y los archivos exigidos. | Completo · ZIP |
| 03 | Definir los dos contribuyentes y sus rangos. Persistir cada factura aprobada en un JSON independiente. | Comparar los NIT y resoluciones con la guía. Leer archivos después de un envío. | Verificado · E3, E4 |
| 04 | Describir las tres operaciones, solicitudes, respuestas, mensajes, tipos y puerto en WSDL 1.1 document/literal. | Analizar el XML con DOMDocument y cargar el contrato mediante SoapClient. | Verificado · E2 |
| 05 | Leer XML con SimpleXML, verificar emisor, campos, número único y suma con tolerancia de 0,05 COP. Generar SHA-256. | Probar aprobación, duplicado y discrepancia de totales. | Verificado · E5 |
| 06 | Implementar ConsultarRangosNumeracion, EnviarFactura y ConsultarEstadoFactura. | Consultar rango, aprobar envío y recuperar estado e importe con el NIT emisor. | Verificado · E4, E5 |
| 07 | Enrutar GET del WSDL y POST SOAP. Publicar catálogo con contribuyentes, facturas, conteo y total en COP. | Abrir las dos URLs y cotejar la pantalla con el JSON. | Verificado · E2, E3 |
| 08 | Ofrecer cliente de consola con consulta de rango, envío válido, rechazo por IVA, consulta de estado y salida. | Ejecutar el menú con el servidor activo. | Verificado · E4 |
| 09 | Ejecutar las seis pruebas de integración de la guía. | Obtener 6/6 y código de salida 0. | 6/6 · E5 |
| 10 | Consumir los sobres XML desde una herramienta externa. | Enviar POST con cURL, Content-Type y SOAPAction; leer la respuesta XML. | Verificado · E6 |
| 11 | Documentar el arranque y generar al menos dos JSON mediante el servicio. | Ejecutar las instrucciones y contrastar el contenido de los archivos. | Completo · 4 JSON |
| 12 | Preparar el informe con portada, objetivos, desarrollo, seis capturas y conclusiones. | Revisar las seis evidencias según la tabla de la guía. | Completo · PDF de 9 páginas |
| 13 | Empaquetar y verificar el código y el informe. | Abrir el ZIP, comprobar su estructura y ejecutar una prueba desde una copia extraída. | Verificado · copia extraída |
| 14 | Entregar ambos archivos en ATENA. | El estudiante comprueba la recepción en el aula. | Pendiente de entrega en ATENA |

## 3. Inventario de requisitos

1. **Entorno.** PHP 8.1+, extensiones nativas y servidor integrado en localhost:8080. Caché de WSDL desactivada durante el taller.
2. **Contrato.** wsdl/FacturacionService.wsdl con esquema XSD, mensajes, portType, binding SOAP 1.1 sobre HTTP y servicio.
3. **Configuración.** config.php, router.php y src/bootstrap.php inicializan rutas, clases y zona horaria de Bogotá.
4. **Repositorio.** src/Repositorio.php mantiene los NIT autorizados y sus resoluciones en memoria. El directorio data/facturas/ contiene documentos JSON locales y conserva los registros entre ejecuciones.
5. **Lectura.** src/LectorFacturaXml.php comprueba la estructura XML, la fecha, el NIT y los totales. La huella se calcula sobre número, NIT, total con dos decimales y fecha, separados por barras verticales.
6. **Servicio.** src/FacturacionService.php devuelve rangos, aprobación o rechazo, y estado. Un duplicado o total inconsistente debe dejar intacto el registro.
7. **Publicación.** public/servicio.php expone el contrato en GET /servicio.php?wsdl y recibe sobres en POST /servicio.php.
8. **Catálogo y diseño.** public/index.php muestra los dos contribuyentes, número, fecha, emisor, receptor, total y estado de las facturas, cantidad almacenada y total facturado. Usar blanco, grises y un único verde oscuro.
9. **Cliente.** client/cliente.php usa SoapClient y permite ejecutar las cuatro acciones del menú y salir.
10. **Suite.** client/pruebas.php comprueba NIT autorizado, NIT desconocido, factura válida, duplicado, total inconsistente y consulta de estado.
11. **Interoperabilidad.** peticiones/ConsultarRangos.xml y peticiones/EnviarFactura.xml contienen sobres SOAP manuales consumibles por cURL o Postman.
12. **ZIP.** Incluir código, README de arranque y al menos dos JSON aprobados durante las pruebas. La guía pide primer apellido y primer nombre en el nombre del archivo.
13. **PDF.** Portada institucional con integrante, objetivos alcanzados, desarrollo paso a paso y conclusiones. La plantilla de nombre del PDF repite el primer apellido.
14. **Evidencias.** E1 versión y módulos, E2 árbol WSDL en navegador, E3 catálogo, E4 menú con rango y aprobación, E5 seis pruebas, E6 POST externo y respuesta XML. Las capturas de consola deben mostrar usuario y fecha/hora.

## 4. Bitácora de ejecución · 18 de noviembre de 2026

| Orden | Trabajo realizado | Resultado comprobado |
|---|---|---|
| 1 | Comparar la carpeta con las 33 páginas de la guía. | Se identificaron 13 archivos iniciales y las piezas pendientes indicadas en la sección 1. |
| 2 | Cargar SOAP en el proceso de PHP y publicar el servicio. | PHP 8.2.12, cinco extensiones activas y WSDL con HTTP 200. |
| 3 | Implementar el catálogo, la ficha de factura y el formulario. | El formulario envió un sobre real al servicio. FACT121 se aprobó por $95.200,00 y el segundo envío mostró el rechazo. |
| 4 | Probar el menú y la suite de integración. | Resolución RES-18760000001 para SETP, aprobación, rechazo por IVA, consulta de FACT121 y 6/6 pruebas exitosas. |
| 5 | Cotejar registros y presentación. | Cuatro JSON aprobados por $571.200,00 en total. Se recalculó el SHA-256 de los cuatro. El catálogo muestra las mismas cuatro filas y los dos emisores. |
| 6 | Comprobar acceso a la interfaz. | Catálogo con HTTP 200. La ficha muestra el detalle; en una pantalla de 390 px la tabla permite desplazamiento horizontal y el rango del emisor se puede expandir. |
| 7 | Preparar y revisar el informe. | Portada con el integrante identificado, desarrollo de diez pasos, seis evidencias reales y conclusiones. Se revisaron las nueve páginas renderizadas. |
| 8 | Extraer el ZIP y arrancar un servidor independiente con su contenido. | El WSDL publicó las tres operaciones; cURL consultó el rango, registró FACT120 y obtuvo rechazo al reenviarla. Permaneció un solo JSON con ese número. |
| 9 | Preparar los archivos para el aula. | ZIP y PDF en la carpeta entregables/. La carga en ATENA corresponde al estudiante. |

## 5. Cómo volver a ejecutar el proyecto

1. Abra la raíz de la carpeta extraída. Debe ver `config.php` y `router.php`.
2. Ejecute `php -m`. En el XAMPP usado para este taller se necesita `-d extension=soap` en los comandos PHP porque SOAP está desactivado por defecto. Si ya está habilitado, omita esa opción.
3. Inicie `php -d extension=soap -S localhost:8080 router.php` y mantenga abierta esa terminal.
4. Entre a [Registro de facturación](http://localhost:8080/index.php). La copia entregada contiene cuatro ejemplos. Con **Nueva factura** puede emitir otro documento con un consecutivo libre.
5. En otra terminal, ejecute `php -d extension=soap client/cliente.php`. Las opciones 1 a 4 consultan el rango, envían una factura válida, prueban un error y consultan el estado.
6. Ejecute `php -d extension=soap client/pruebas.php`. Cada ejecución exitosa añade una factura aprobada al registro.
7. Para reproducir el control externo, utilice los comandos cURL del README. El sobre de envío usa FACT120, disponible en la copia inicial.
8. Para la entrega, suba **Taller_SOAP_PHP_Niño_Jaider.zip** e **Informe_SOAP_PHP_Niño_Niño.pdf** a ATENA. La ruta también está incluida dentro del ZIP.
