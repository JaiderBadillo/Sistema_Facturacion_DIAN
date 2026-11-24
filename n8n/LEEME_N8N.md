# Envío de facturas por correo con n8n

> **Alternativa sin n8n:** si en `config.local.php` hay una clave de Brevo, Folio envía el correo directamente por la API de Brevo y no usa n8n (ver el README, sección 5). Esta guía aplica cuando se prefiere n8n.

Cuando una factura se aprueba y trae la orden de enviarla por correo, Folio avisa a n8n y n8n envía al cliente el **PDF** y el **XML**.

## 1. Cómo lo hacen las empresas (y qué se imitó)

Lo siguiente se verificó en la normativa de la DIAN (Resolución 000042 de 2020). No se verificó el proceso interno de Éxito u otro comercio concreto.

| Práctica | En la norma | En Folio |
|---|---|---|
| La factura se entrega al comprador en su formato electrónico (XML) | Sí | Se adjunta el XML original |
| El comprador puede recibir además la representación gráfica (PDF) | Sí | Se adjunta el PDF |
| El correo del comprador se reporta dentro del XML | Sí | `<Receptor><Correo>` en el XML |
| Si el comprador no pide factura electrónica, se imprime | Sí | El check solo se activa si el cliente tiene correo |
| Código único (CUFE) y código QR en la factura | Sí | SHA-256 como equivalente del CUFE y un **código QR** que abre `/verificar.php` |
| La factura y la respuesta de validación van en un contenedor (`AttachedDocument`) | Sí | **Contenedor simulado**: XML con la factura y una respuesta de validación de Folio |
| Si la validación o la entrega fallan, hay contingencia (reenvío dentro de 48 h) | Sí (Res. 030 de 2019) | **Contingencia del correo**: reintentos hasta 48 h |
| El asunto y el cuerpo identifican emisor, número, fecha y total | Práctica común | Sí |

Fuentes: [Resolución DIAN 0042 de 2020](https://normograma.dian.gov.co/dian/compilacion/docs/resolucion_dian_0042_2020.htm) y [DIAN, cómo funciona](https://micrositios.dian.gov.co/sistema-de-facturacion-electronica/presentacion-recepcion-de-facturas-electronica/).

## 2. Cómo funciona

```
Navegador (check "Enviar por correo")
   │  SOAP EnviarFactura  (XML con <Correo> y <EnvioCorreo>true</EnvioCorreo>)
   ▼
Folio valida, guarda el JSON y el XML, y llama al webhook de n8n
   ▼
n8n:  descarga el PDF (con QR)        ┐
      descarga el XML contenedor      ┴─ une ─► envía el correo (SMTP, 3 intentos) ─► avisa a Folio: ENVIADO / FALLIDO
```

- El PDF lo genera Folio al pedirlo (`/factura.php?numero=...&formato=pdf`) e incluye un código QR que apunta a `/verificar.php?cufe=...`.
- El XML que se adjunta es el **contenedor** (`formato=xml`). El XML original sigue disponible en `formato=factura` y se conserva en `data/xml/`.
- n8n avisa el resultado a `/correo.php` con la cabecera `X-Folio-Token`. La ficha de la factura muestra el estado: En cola, Enviado, Falló o "n8n no recibió la orden".
- Si algo falla, **la factura se aprueba igual** y entra en contingencia (ver sección 3).

## 3. Contingencia: qué pasa si el correo falla

La factura **nunca** se pierde por un fallo del correo. Si n8n está apagado o el SMTP falla:

1. n8n reintenta el envío 3 veces (con 3 s entre intentos). Si agota los intentos, avisa a Folio.
2. La factura queda en estado **CONTINGENCIA** con el último error y un **plazo de 48 horas** (la misma ventana de la contingencia de la DIAN, Res. 030 de 2019).
3. Folio reintenta cada vez que se lo piden, con un mínimo de 60 s entre intentos de la misma factura:
   - **Automático:** el segundo flujo de n8n (`folio-reintentos.workflow.json`) llama a `/reintentar.php` cada 10 minutos.
   - **Manual:** el botón **Reintentar envío ahora** de la ficha de la factura.
4. Si pasa el plazo sin entregar, la factura queda **VENCIDA** y deja de reintentarse.

Estados que muestra la ficha: En cola, Enviado, En contingencia, Vencida.

## 4. Puesta en marcha

1. **Instalar y arrancar n8n** (requiere Node 18+):
   ```powershell
   npx n8n
   ```
   Abra http://localhost:5678 y cree la cuenta local.
2. **Importar los dos flujos:** Workflows ▸ Import from file ▸ `n8n/folio-envio-factura.workflow.json` y `n8n/folio-reintentos.workflow.json`.
3. **Crear la credencial SMTP** y asignarla al nodo *Enviar correo (SMTP)*. Opciones:
   - **Mailtrap** (recomendado para el laboratorio): los correos quedan en una bandeja de pruebas y no llegan a nadie.
   - **Gmail:** servidor `smtp.gmail.com`, puerto 465 con SSL, usuario su correo y una **contraseña de aplicación** (no la clave normal).
4. **Activar los dos flujos** (interruptor *Active*). El de reintentos usa la URL `http://localhost:8080/reintentar.php` y el token `folio-uts-2026`; ajústelos en su nodo si cambió el puerto o el secreto. El webhook de producción queda en `http://localhost:5678/webhook/folio-factura`, que ya es el valor por defecto de `config.php`.
5. Arrancar Folio como siempre. En **Nueva factura**, elija un cliente con correo, deje marcado el check y envíe.

### Variables opcionales
| Variable | Para qué | Valor por defecto |
|---|---|---|
| `FOLIO_N8N_WEBHOOK` | URL del webhook si n8n usa otra | `http://localhost:5678/webhook/folio-factura` |
| `FOLIO_TOKEN` | Secreto compartido del callback | `folio-uts-2026` (cámbielo fuera del laboratorio) |

## 5. Qué se probó y qué no

Todo con **n8n 2.48.7 real**, un servidor SMTP local que solo captura los correos y la aplicación en un puerto aparte.

- **Envío normal:** el correo llegó con dos adjuntos, `DSAN1000.pdf` y `DSAN1000-contenedor.xml`. El PDF abre bien y su **QR decodifica a la URL de verificación**. El contenedor es un `AttachedDocument` con dos bloques: la factura y la respuesta de validación. `/verificar.php` muestra "Factura válida" y responde 404 para un código falso.
- **Contingencia automática:** con el SMTP apagado la ficha pasó a "En contingencia" (n8n lo intentó 3 veces). Al volver a encender el SMTP, el flujo programado la entregó solo en 65 s (intento 2).
- **n8n apagado:** dos facturas se aprobaron y quedaron en contingencia. Una con el plazo vencido pasó a "Vencida". El botón manual reportó el fallo mientras el SMTP seguía apagado y entregó la factura al repetirlo con el SMTP encendido (intento 3).
- **Seguridad del reintento:** sin token ni número de factura en contingencia responde 422.
- **Suite de la guía:** 6/6.
- **Generador de QR:** sus matrices son idénticas, módulo a módulo, a las de la librería `qrcode` de Python (versión 7, las 8 máscaras). Con el lector clásico de OpenCV una máscara concreta (la 2) no se decodifica, ni siquiera con la librería de referencia; con el lector Aruco de OpenCV sí. Si algún lector de su celular no leyera un QR, pruebe otro lector.
- **No probado:** envío contra un proveedor SMTP real, y el QR con la cámara de un celular.

### Limitaciones del contenedor y el QR (simulación)
- El contenedor sigue la **idea** del `AttachedDocument` de la DIAN, pero con estructura simplificada, **sin firma digital** y con un validador propio (Folio). No tiene validez ante la DIAN.
- El QR apunta a `base_url` de `config.php` (`localhost`), así que solo abre desde el equipo donde corre Folio.

## 6. Datos de ejemplo
Los clientes de ejemplo usan correos `@example.com` (dominio reservado, no existe). Para probar con un correo real, agregue un cliente nuevo desde **Clientes ▸ Agregar cliente** con su propio correo.
