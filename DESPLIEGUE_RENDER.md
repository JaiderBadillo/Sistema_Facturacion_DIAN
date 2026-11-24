# Despliegue en Render con Brevo

Folio se despliega en Render con **Docker** (Render no tiene entorno nativo de PHP) y envía los correos por la **API HTTPS de Brevo**. Render bloquea los puertos SMTP (25, 465 y 587) en el plan gratuito, así que el Gmail por SMTP que se usa en local no funciona allí.

## Qué trae el repositorio para esto
| Archivo | Para qué |
|---|---|
| `Dockerfile` | PHP 8.2 con la extensión `soap`; arranca `php -S 0.0.0.0:$PORT router.php` |
| `render.yaml` | Blueprint: servicio web, variables de entorno y datos |
| `.gitignore`, `.dockerignore` | Dejan fuera `config.local.php`, `data/`, `entregables/` y archivos ajenos |
| `router.php` | Pide usuario y contraseña (obligatorio en Render) |

## 1. Brevo: remitente y clave API
1. Entre a su cuenta de [Brevo](https://app.brevo.com) (la misma de Vincula sirve).
2. **Remitente:** en *Senders, Domains & Dedicated IPs* ▸ *Senders*, tenga un remitente **verificado**. Es el correo que figurará en el "De" y el que irá en `FOLIO_BREVO_REMITENTE`. Si el remitente no está verificado, Brevo rechaza los envíos.
3. **Clave API:** en *SMTP & API* ▸ *API Keys* ▸ *Generate a new API key*, nómbrela (por ejemplo `folio`) y **cópiela en ese momento**: Brevo no la vuelve a mostrar. Es la clave de la API v3, no la contraseña SMTP.
4. Si más adelante la API responde 401 con un aviso de IP no reconocida, revise *Security* ▸ *Authorized IPs*; Render no usa una IP fija.

## 2. Subir el despliegue a Render
1. En [render.com](https://render.com), *New* ▸ **Blueprint**, conecte GitHub y elija el repositorio `Sistema_Facturacion_DIAN`.
2. Render lee `render.yaml` y pide los valores de las variables marcadas como secretas (solo las dos de Brevo). Complételas:

| Variable | Qué poner |
|---|---|
| `FOLIO_ACCESO_ABIERTO` | `true` deja el sitio abierto sin ingreso (ya viene puesto) |
| `FOLIO_BREVO_KEY` | La clave API v3 de Brevo del paso 1 |
| `FOLIO_BREVO_REMITENTE` | El correo del remitente verificado en Brevo |

   `FOLIO_TOKEN` lo genera Render y `FOLIO_DATA_DIR` ya viene puesto.
3. Pulse *Apply* y espere el primer despliegue (varios minutos).
4. Abra la URL `https://…onrender.com`: entra directo. (Con `FOLIO_ACCESO_ABIERTO=false` aparece una página de ingreso con usuario y contraseña; la sesión dura 12 horas y `/salir.php` la cierra.)

La URL pública la toma Folio de `RENDER_EXTERNAL_URL`, que entrega Render. De ella salen el QR, los enlaces y la dirección del WSDL. Si algún enlace saliera con otra dirección, cree la variable `FOLIO_BASE_URL` con la URL completa (sin barra final).

## 3. Comprobar que funciona
1. Entre al sitio, vaya a **Clientes** de un emisor y agregue un cliente con **su propio correo**.
2. Cree una factura a ese cliente con el check **Enviar la factura al correo** marcado.
3. La ficha debe decir **Enviado**. En Brevo, *Transactional* ▸ *Logs* muestra el correo.
4. Si dice **En contingencia**, el motivo aparece en la ficha. Con `HTTP/1.1 401` revise la clave y el remitente; con `Sin respuesta de Brevo`, revise el estado del servicio. Corrija y use **Reintentar envío ahora**.
5. Escanee el QR del PDF: abre `/verificar.php`, que es público a propósito.

## Acceso: el sitio está abierto
Por decisión del dueño, el sitio abre **sin usuario ni contraseña** (`FOLIO_ACCESO_ABIERTO=true`). Cualquiera que conozca la URL puede crear emisores, clientes y facturas, y **hacer que la cuenta de Brevo envíe correos con PDF a cualquier dirección**. Para reducir el daño:
- Hay un **tope de 30 correos por día** (`FOLIO_MAX_CORREOS_DIA`), muy por debajo de los 300 de Brevo gratis.
- El sitio envía `noindex` y `robots.txt` para que los buscadores no lo listen. No es una protección: quien tenga la URL entra.
- No use datos reales de personas.

**Para volver a pedir ingreso:** en *Environment* ponga `FOLIO_ACCESO_ABIERTO=false` y cree `FOLIO_USUARIO` y `FOLIO_CLAVE`. Aparece la página de ingreso. Si faltan esas dos, el sitio responde 503 en lugar de quedar abierto por error.

## Límites del plan gratuito
- **Los datos son temporales.** Sin disco persistente se pierden al reiniciar o redesplegar: emisores y clientes creados desde la web, facturas y estado de los correos. Solo permanecen los 4 emisores de ejemplo. Para conservarlos hace falta un plan de pago con disco (ver `render.yaml`).
- **El servicio se duerme** tras un rato sin visitas y tarda en despertar.
- **Brevo gratuito:** 300 correos al día.
- **No hay n8n en Render.** Con Brevo no hace falta; los reintentos se hacen con el botón de la ficha.
- Folio es una simulación académica sin validez ante la DIAN. Protéjalo con usuario y contraseña y no publique claves.
