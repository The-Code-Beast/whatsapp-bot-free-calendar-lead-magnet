# Bookline Installation and Configuration Guide

This guide explains how to install the application, generate the required keys, and connect WhatsApp Cloud API and Google Calendar.

> **Important:** never publish your `.env` file, Meta app secret, access tokens, or Google OAuth client secret. The `.env` file is already excluded from Git.

## 1. Requirements

- PHP 8.3 or a later version compatible with Laravel 13.
- Composer.
- Node.js and npm.
- MySQL 8 or SQLite for development.
- A [Meta for Developers](https://developers.facebook.com/apps/) account.
- A Meta business portfolio and WhatsApp Business Account.
- A Google Cloud account if you plan to synchronize Google Calendar.
- A public HTTPS domain. You can use ngrok for local testing.

An OpenAI API key is not required. The application's booking assistant uses its own conversation flow.

## 2. Install the project

Run the following commands in a terminal:

```bash
git clone REPOSITORY_URL
cd whatsapp-bot-free-calendar-lead-magnet
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`php artisan key:generate` creates `APP_KEY`. This key encrypts data such as the WhatsApp and Google tokens stored by the application. Do not change it after credentials have been saved in production.

### Option A: SQLite for development

```bash
touch database/database.sqlite
```

In `.env`, replace the `DB_*` variables with:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/ABSOLUTE/PATH/TO/PROJECT/database/database.sqlite
```

### Option B: MySQL

Create an empty database first, then configure:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bookline
DB_USERNAME=YOUR_USERNAME
DB_PASSWORD=YOUR_PASSWORD
```

Then run:

```bash
php artisan migrate
npm run build
```

## 3. Run the application locally

Use two terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

Open `http://localhost:8000`, create an account, and complete these sections first:

1. Business settings and time zone.
2. Services.
3. Availability.
4. WhatsApp.
5. Google Calendar, if you plan to use it.

## 4. Create a public HTTPS URL for testing

Meta must be able to reach the webhook over the internet. With the Laravel server running, open another terminal:

```bash
ngrok http 8000
```

ngrok will display a URL similar to:

```text
https://example.ngrok-free.app
```

Update `.env` with the complete URL, including **`https://`**:

```dotenv
APP_URL=https://example.ngrok-free.app
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/integrations/google-calendar/callback"
```

Clear the cached configuration:

```bash
php artisan optimize:clear
```

If the ngrok URL changes, update `APP_URL`, the Meta callback URL, and the Google redirect URI. Using an `http://` endpoint from an `https://` page causes *Mixed Content* errors and blocks login requests and webhooks.

## 5. Required environment variables

| Variable | Required | Source |
| --- | --- | --- |
| `APP_KEY` | Yes | Run `php artisan key:generate` |
| `APP_URL` | Yes | Your HTTPS domain or ngrok URL |
| `WHATSAPP_VERIFY_TOKEN` | For WhatsApp | You generate it; Meta must receive the exact same value |
| `META_APP_SECRET` | For WhatsApp | Meta for Developers → your app → App settings → Basic → App secret |
| `WHATSAPP_GRAPH_VERSION` | For WhatsApp | The default value is already `v23.0` |
| `GOOGLE_CALENDAR_CLIENT_ID` | For Calendar | Google Cloud OAuth 2.0 client |
| `GOOGLE_CALENDAR_CLIENT_SECRET` | For Calendar | Google Cloud OAuth 2.0 client |
| `GOOGLE_CALENDAR_REDIRECT_URI` | For Calendar | `${APP_URL}/integrations/google-calendar/callback` |

Generate a secure webhook verification token:

```bash
openssl rand -hex 32
```

Add the result to `.env`:

```dotenv
WHATSAPP_VERIFY_TOKEN=PASTE_THE_RESULT_HERE
```

This token does not come from Meta. You create it and then enter the exact same value in Meta's webhook configuration.

## 6. Create and configure the Meta application

Meta changes its interface frequently, but the required credentials remain the same.

### 6.1 Create the correct app

1. Open [Meta for Developers](https://developers.facebook.com/apps/) and select **Create app**.
2. Select a use case that supports **WhatsApp**, or create a **Business** app.
3. Connect the app to your Meta business portfolio.
4. Under **Add products** or **Use cases**, add **WhatsApp** and open **API Setup**.

If the menu only shows “Facebook Login for Business” and does not show WhatsApp, the app does not yet have the WhatsApp product or use case. Add it under **Use cases**. If Meta does not offer that option for the app, create a new Business app with WhatsApp.

### 6.2 Obtain the App Secret

1. In the Meta app, open **App settings → Basic**.
2. Find **App secret** and select **Show**.
3. Add it to `.env`:

```dotenv
META_APP_SECRET=PASTE_THE_APP_SECRET_HERE
```

Do not use the **App ID** for this variable. Then run:

```bash
php artisan optimize:clear
```

### 6.3 Configure the webhook

1. Sign in to Bookline and open **Settings → WhatsApp**.
2. Copy the URL shown in the **Meta webhook** card. It must end with:

   ```text
   /webhooks/whatsapp
   ```

3. In Meta, open **WhatsApp → Configuration** and find **Webhook**.
4. Paste the URL into **Callback URL**.
5. Paste the value of `WHATSAPP_VERIFY_TOKEN` into **Verify token**.
6. Save it or select **Verify and save**.
7. In the webhook fields, subscribe to **`messages`**.

The `messages` subscription is the one this bot needs to receive messages and related statuses. You do not need to subscribe to every available field.

The endpoint handles Meta's `GET` verification request and incoming `POST` notifications. Incoming notifications are also validated using a signature generated from `META_APP_SECRET`.

### 6.4 Obtain the Phone Number ID and WABA ID

Under **WhatsApp → API Setup**, you will find:

- **Phone number ID**: required by Bookline.
- **WhatsApp Business Account ID (WABA ID)**: optional in Bookline, but recommended.
- **Temporary access token**: suitable for an initial test, but it expires.

Do not enter the visible phone number in the **Phone number ID** field. Meta assigns a separate numeric identifier to the phone number.

When registering a real number:

- Use the international E.164 format.
- The number must be able to receive a verification code by SMS or phone call.
- A fictional number such as `+1 (555) ...` cannot be registered.
- If the number is already connected to another WhatsApp configuration, follow Meta's applicable migration or coexistence process first.

### 6.5 Create a permanent token

The token under **API Setup** works for initial testing, but production should use a system-user token:

1. Open [Meta Business Settings](https://business.facebook.com/settings/).
2. Go to **Users → System users**.
3. Create a system user. An administrator role is commonly used while completing the initial setup.
4. Assign the Meta app and WhatsApp Business Account as assets for that user.
5. Select **Generate new token**, choose the app, and grant:
   - `whatsapp_business_messaging`
   - `whatsapp_business_management`
6. Copy the token immediately and store it in a secrets manager.

The exact menu names can vary depending on Meta's current interface and your business permissions. See the official [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started) and [System Users](https://developers.facebook.com/docs/whatsapp/embedded-signup/manage-accounts/system-users) documentation.

### 6.6 Connect the number in Bookline

1. Open **Settings → WhatsApp**.
2. Enter the **Phone number ID**.
3. Enter the **WABA ID** if available.
4. Paste the permanent access token.
5. Enable **Automatic booking replies**.
6. Select **Connect WhatsApp**.

Bookline checks the credentials with the Graph API before saving them. The connection will be rejected if the token cannot access the specified Phone Number ID. The token is encrypted in the database and is never displayed again.

## 7. Configure Google Calendar

Google Calendar is optional, but it allows the application to check availability and create, update, or cancel events.

1. Open [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select a project.
3. Open **APIs & Services → Library** and enable **Google Calendar API**.
4. Under **Google Auth Platform**, configure Branding, Audience, and Data Access.
5. If the app is in testing mode, add your email address under **Test users**.
6. Open **Google Auth Platform → Clients → Create Client**.
7. Select **Web application**.
8. Add the exact URL below as an **Authorized redirect URI**:

   ```text
   https://YOUR_DOMAIN/integrations/google-calendar/callback
   ```

9. Add the Client ID and Client Secret to `.env`:

```dotenv
GOOGLE_CALENDAR_CLIENT_ID=PASTE_THE_CLIENT_ID_HERE
GOOGLE_CALENDAR_CLIENT_SECRET=PASTE_THE_CLIENT_SECRET_HERE
GOOGLE_CALENDAR_REDIRECT_URI="${APP_URL}/integrations/google-calendar/callback"
```

This application does not need a Google API key; it uses OAuth 2.0. The redirect URI must match exactly, including the `http` or `https` scheme, domain, port, path, and absence of a trailing slash. See Google's official [OAuth 2.0 for web server applications](https://developers.google.com/identity/protocols/oauth2/web-server) guide.

Then run:

```bash
php artisan optimize:clear
```

In Bookline, open **Settings → Workspace**, select **Connect Google Calendar**, authorize the requested permissions, and choose the calendar that should receive bookings.

## 8. Test the complete booking flow

1. Confirm that `/up` responds successfully on the public domain.
2. In Meta, confirm that the webhook is active and subscribed to `messages`.
3. Send a message from an allowed phone number to the WhatsApp Business number.
4. The bot should display services as numbered options.
5. Select a service and time by replying with its number.
6. Confirm the booking.
7. Verify that it appears under **Appointments**, **Conversations**, and Google Calendar.

When using Meta's test number, first add the customer's phone number to the allowed recipients list under **API Setup**.

## 9. Automations and production

Reminders and follow-up messages are checked every five minutes. In production, configure Laravel's scheduler to run every minute:

```cron
* * * * * cd /PATH/TO/PROJECT && php artisan schedule:run >> /dev/null 2>&1
```

For production, also:

- Use a stable HTTPS domain instead of a temporary ngrok URL.
- Configure `APP_ENV=production`, `APP_DEBUG=false`, and the final `APP_URL`.
- Store secrets in your hosting provider's secure environment variables.
- Run `php artisan migrate --force` and `npm run build` during deployment.
- Run `php artisan optimize` after setting the environment variables.
- Keep `APP_KEY` stable and backed up securely.
- Complete any applicable Meta business information, verification, and review requirements before messaging real customers.

## 10. Troubleshooting

### Mixed Content or routes use `http://`

Confirm that `APP_URL` starts with `https://`, then run `php artisan optimize:clear`. Restart the server if it continues using the previous URL.

### Meta does not show WhatsApp

The app does not have the WhatsApp product or use case. Add it under **Use cases**, or create a new Business app with WhatsApp.

### “Unsupported post request” or “missing permissions”

Confirm that the token belongs to the same app and business, the system user has been assigned the app and WABA, both WhatsApp permissions were granted, and you are using the **Phone number ID** rather than the App ID or WABA ID.

### Meta cannot verify the callback

Confirm that the URL is publicly available over HTTPS, ends in `/webhooks/whatsapp`, the verification token matches character for character, and `php artisan optimize:clear` was run after editing `.env`.

### Google displays `redirect_uri_mismatch`

Copy the exact value of `GOOGLE_CALENDAR_REDIRECT_URI` into **Authorized redirect URIs** in Google Cloud. Any difference in scheme, domain, port, path, or trailing slash causes this error.

### The bot receives messages but does not reply

Confirm that the connection is active, **Automatic booking replies** is enabled, services and available hours exist, and inspect the logs:

```bash
php artisan pail
```

