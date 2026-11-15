# Guía de instalación y configuración de Bookline

Esta guía explica cómo instalar la aplicación, crear las llaves necesarias y conectar WhatsApp Cloud API y Google Calendar.

> **Importante:** nunca publiques el archivo `.env`, el secreto de Meta, los tokens de acceso ni el secreto OAuth de Google. El archivo `.env` ya está excluido de Git.

## 1. Qué necesitas

- PHP 8.3 o posterior compatible con Laravel 13.
- Composer.
- Node.js y npm.
- MySQL 8 o SQLite para desarrollo.
- Una cuenta de [Meta for Developers](https://developers.facebook.com/apps/).
- Un portafolio comercial de Meta y una cuenta de WhatsApp Business.
- Una cuenta de Google Cloud si vas a sincronizar Google Calendar.
- Un dominio HTTPS público. Para pruebas locales puedes usar ngrok.

No se necesita una llave de OpenAI. El asistente de reservas de esta aplicación usa su propio flujo de conversación.

## 2. Instalar el proyecto

Desde una terminal:

```bash
git clone URL_DEL_REPOSITORIO
cd whatsapp-bot-free-calendar-lead-magnet
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`php artisan key:generate` crea `APP_KEY`. Esta llave cifra, entre otras cosas, los tokens de WhatsApp y Google guardados por la aplicación. No la cambies después de guardar credenciales en producción.

### Opción A: base de datos SQLite para desarrollo

```bash
touch database/database.sqlite
```

En `.env`, reemplaza las variables `DB_*` por:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/RUTA/ABSOLUTA/AL/PROYECTO/database/database.sqlite
```

### Opción B: base de datos MySQL

Crea primero una base de datos vacía y configura:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bookline
DB_USERNAME=TU_USUARIO
DB_PASSWORD=TU_PASSWORD
```

Después ejecuta:

```bash
php artisan migrate
npm run build
```

## 3. Ejecutar la aplicación localmente

Usa dos terminales:

```bash
php artisan serve
```

```bash
npm run dev
```

Abre `http://localhost:8000`, crea una cuenta y completa primero:

1. Configuración del negocio y zona horaria.
2. Servicios.
3. Disponibilidad.
4. WhatsApp.
5. Google Calendar, si lo vas a usar.

## 4. Crear una URL HTTPS pública para pruebas

Meta necesita acceder al webhook desde internet. Con el servidor de Laravel activo, abre otra terminal:

```bash
ngrok http 8000
```

ngrok mostrará una URL parecida a:

```text
https://ejemplo.ngrok-free.app
```

Actualiza `.env` con esa URL completa y **con `https://`**:

```dotenv
APP_URL=https://ejemplo.ngrok-free.app
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/integrations/google-calendar/callback"
```

Luego limpia la configuración almacenada:

```bash
php artisan optimize:clear
```

Si cambia la URL de ngrok, debes actualizar `APP_URL`, el callback de Meta y el redirect URI de Google. Usar `http://` dentro de una página `https://` provoca errores de *Mixed Content* y bloquea el inicio de sesión y los webhooks.

## 5. Variables necesarias

| Variable | Obligatoria | De dónde sale |
| --- | --- | --- |
| `APP_KEY` | Sí | `php artisan key:generate` |
| `APP_URL` | Sí | Tu dominio HTTPS o URL de ngrok |
| `WHATSAPP_VERIFY_TOKEN` | Para WhatsApp | Lo generas tú; Meta debe recibir exactamente el mismo valor |
| `META_APP_SECRET` | Para WhatsApp | Meta for Developers → tu app → App settings → Basic → App secret |
| `WHATSAPP_GRAPH_VERSION` | Sí para WhatsApp | Ya tiene `v23.0` como valor predeterminado |
| `GOOGLE_CALENDAR_CLIENT_ID` | Para Calendar | Cliente OAuth 2.0 de Google Cloud |
| `GOOGLE_CALENDAR_CLIENT_SECRET` | Para Calendar | Cliente OAuth 2.0 de Google Cloud |
| `GOOGLE_CALENDAR_REDIRECT_URI` | Para Calendar | `${APP_URL}/integrations/google-calendar/callback` |

Genera un token de verificación seguro para el webhook:

```bash
openssl rand -hex 32
```

Pega el resultado en `.env`:

```dotenv
WHATSAPP_VERIFY_TOKEN=PEGA_AQUI_EL_RESULTADO
```

Este token no viene de Meta: tú lo inventas o generas, y después copias exactamente el mismo texto en la configuración del webhook de Meta.

## 6. Crear y configurar la aplicación de Meta

La interfaz de Meta cambia con frecuencia, pero los datos requeridos son los mismos.

### 6.1 Crear la app correcta

1. Abre [Meta for Developers](https://developers.facebook.com/apps/) y elige **Create app**.
2. Selecciona un caso de uso que permita **WhatsApp** o crea una app de tipo **Business**.
3. Vincula la app con tu portafolio comercial.
4. En **Add products** o **Use cases**, agrega **WhatsApp** y entra a **API Setup**.

Si en el menú solamente aparece “Facebook Login for Business” y no aparece WhatsApp, esa app todavía no tiene agregado el producto/caso de uso de WhatsApp. Agrégalo desde **Use cases**; si Meta no ofrece esa opción para la app, crea una nueva app Business con WhatsApp.

### 6.2 Obtener el App Secret

1. Dentro de la app ve a **App settings → Basic**.
2. Busca **App secret** y elige **Show**.
3. Guárdalo en `.env`:

```dotenv
META_APP_SECRET=PEGA_AQUI_EL_APP_SECRET
```

No uses el **App ID** en esta variable. Después ejecuta:

```bash
php artisan optimize:clear
```

### 6.3 Configurar el webhook

1. Inicia sesión en Bookline y abre **Configuración → WhatsApp**.
2. Copia la URL que aparece en la tarjeta **Webhook de Meta**. Debe terminar en:

   ```text
   /webhooks/whatsapp
   ```

3. En Meta abre **WhatsApp → Configuration** y busca **Webhook**.
4. En **Callback URL**, pega la URL copiada.
5. En **Verify token**, pega el mismo valor de `WHATSAPP_VERIFY_TOKEN`.
6. Guarda o elige **Verify and save**.
7. En los campos del webhook, suscríbete a **`messages`**.

Para este bot, `messages` es la suscripción necesaria para recibir mensajes y estados relacionados. No necesitas seleccionar todos los campos.

El endpoint acepta tanto la verificación `GET` de Meta como las notificaciones `POST`. Las notificaciones también se validan con la firma creada a partir de `META_APP_SECRET`.

### 6.4 Obtener Phone Number ID y WABA ID

En **WhatsApp → API Setup** encontrarás:

- **Phone number ID**: obligatorio en Bookline.
- **WhatsApp Business Account ID (WABA ID)**: opcional en Bookline, pero conviene guardarlo.
- **Temporary access token**: sirve para una prueba inicial, pero expira.

No escribas el número telefónico en el campo **Phone number ID**. Meta entrega un identificador numérico distinto al número visible.

Para registrar un número real:

- Usa formato internacional E.164.
- El número debe poder recibir el código de verificación por SMS o llamada.
- Un número ficticio como `+1 (555) ...` no se puede registrar.
- Si el número ya pertenece a otra configuración de WhatsApp, primero sigue el proceso de migración o coexistencia que corresponda en Meta.

### 6.5 Crear un token permanente

El token temporal de **API Setup** es útil para probar, pero para producción crea un usuario del sistema:

1. Abre [Meta Business Settings](https://business.facebook.com/settings/).
2. Ve a **Users → System users**.
3. Crea un usuario del sistema, normalmente con rol de administrador para completar la configuración.
4. Asígnale como activos la aplicación y la cuenta de WhatsApp Business.
5. Elige **Generate new token**, selecciona tu app y concede:
   - `whatsapp_business_messaging`
   - `whatsapp_business_management`
6. Copia el token inmediatamente y guárdalo en un administrador de secretos.

Los nombres exactos de los menús pueden variar según la interfaz y permisos de tu portafolio. Consulta la documentación oficial de [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started) y [System Users](https://developers.facebook.com/docs/whatsapp/embedded-signup/manage-accounts/system-users).

### 6.6 Conectar el número en Bookline

1. Abre **Configuración → WhatsApp**.
2. Pega **Phone number ID**.
3. Pega **WABA ID** si lo tienes.
4. Pega el token permanente.
5. Activa **Respuestas automáticas de reservas**.
6. Presiona **Conectar WhatsApp**.

Bookline consulta la Graph API antes de guardar. Si el token no tiene acceso a ese Phone Number ID, la conexión será rechazada. El token se cifra en la base de datos y no vuelve a mostrarse.

## 7. Configurar Google Calendar

Google Calendar es opcional, pero permite comprobar disponibilidad y crear, actualizar o cancelar eventos.

1. Abre [Google Cloud Console](https://console.cloud.google.com/).
2. Crea o selecciona un proyecto.
3. Ve a **APIs & Services → Library** y habilita **Google Calendar API**.
4. En **Google Auth Platform**, configura Branding, Audience y Data Access.
5. Si la app está en modo de prueba, agrega tu correo en **Test users**.
6. Ve a **Google Auth Platform → Clients → Create Client**.
7. Selecciona **Web application**.
8. Agrega como **Authorized redirect URI** la URL exacta:

   ```text
   https://TU_DOMINIO/integrations/google-calendar/callback
   ```

9. Copia el Client ID y Client Secret a `.env`:

```dotenv
GOOGLE_CALENDAR_CLIENT_ID=PEGA_AQUI_EL_CLIENT_ID
GOOGLE_CALENDAR_CLIENT_SECRET=PEGA_AQUI_EL_CLIENT_SECRET
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/integrations/google-calendar/callback"
```

No hace falta crear una API key de Google para esta aplicación; usa OAuth 2.0. El redirect URI debe coincidir exactamente, incluyendo `http`/`https`, dominio, puerto, ruta y ausencia de `/` final. Consulta la guía oficial de [OAuth 2.0 para aplicaciones web](https://developers.google.com/identity/protocols/oauth2/web-server).

Después:

```bash
php artisan optimize:clear
```

En Bookline abre **Configuración → Espacio de trabajo**, presiona **Conectar Google Calendar**, autoriza los permisos y elige el calendario que recibirá las reservas.

## 8. Probar el flujo completo

1. Confirma que `/up` responde correctamente en tu dominio público.
2. En Meta, verifica que el webhook figure como activo y que `messages` esté suscrito.
3. Envía un mensaje desde un número permitido hacia el número de WhatsApp Business.
4. El bot debe mostrar los servicios como opciones numeradas.
5. Selecciona un servicio y un horario usando su número.
6. Confirma la reserva.
7. Comprueba que aparece en **Citas**, **Conversaciones** y Google Calendar.

Cuando uses el número de prueba de Meta, agrega primero el teléfono del cliente a la lista de destinatarios permitidos en **API Setup**.

## 9. Automatizaciones y producción

Los recordatorios y seguimientos se revisan cada cinco minutos. En producción configura el scheduler de Laravel para ejecutar cada minuto:

```cron
* * * * * cd /RUTA/AL/PROYECTO && php artisan schedule:run >> /dev/null 2>&1
```

Para producción también debes:

- Usar un dominio HTTPS estable, no una URL temporal de ngrok.
- Configurar `APP_ENV=production`, `APP_DEBUG=false` y el `APP_URL` definitivo.
- Guardar secretos en las variables seguras del proveedor de hosting.
- Ejecutar `php artisan migrate --force` y `npm run build` durante el despliegue.
- Ejecutar `php artisan optimize` después de establecer las variables.
- Mantener `APP_KEY` estable y respaldada de forma segura.
- Completar en Meta la información comercial, verificación y revisión que aplique antes de enviar mensajes a clientes reales.

## 10. Solución rápida de errores

### Mixed Content o las rutas usan `http://`

Verifica que `APP_URL` empiece con `https://` y ejecuta `php artisan optimize:clear`. Reinicia el servidor si continúa usando la URL anterior.

### Meta no muestra WhatsApp

La app no tiene el producto/caso de uso de WhatsApp. Agrégalo desde **Use cases** o crea una app Business nueva con WhatsApp.

### “Unsupported post request” o “missing permissions”

Comprueba que el token pertenece a la misma app/empresa, que el usuario del sistema tiene asignados la app y el WABA, que concediste los dos permisos de WhatsApp y que usas el **Phone number ID**, no el App ID ni el WABA ID.

### Meta no puede verificar el callback

Comprueba que la URL sea pública por HTTPS, que termine en `/webhooks/whatsapp`, que el token coincida carácter por carácter y que ejecutaste `php artisan optimize:clear` después de editar `.env`.

### Google muestra `redirect_uri_mismatch`

Copia el valor exacto de `GOOGLE_CALENDAR_REDIRECT_URI` a **Authorized redirect URIs** en Google Cloud. Una diferencia en esquema, dominio, puerto, ruta o `/` final causa el error.

### El bot recibe mensajes pero no contesta

Comprueba que la conexión figure activa, que **Respuestas automáticas** esté habilitado, que existan servicios y horarios disponibles, y revisa:

```bash
php artisan pail
```

