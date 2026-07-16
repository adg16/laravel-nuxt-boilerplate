# Email

## Local development — Mailpit

Dev mail goes to **Mailpit** (the `mailpit` service in the compose override), a local SMTP catcher — **nothing leaves the machine**, so you can safely test invitation and password-reset emails.

`backend/.env` points `MAIL_MAILER=smtp` at `mailpit:1025`. Read captured mail in the web inbox at **`http://localhost:8025`** (override the port with `MAILPIT_PORT` in the root `.env`).

Outbound mail is **queued**, so the [`queue` worker](queues.md) must be running for messages to arrive.

> **[.env force-recreate gotcha](architecture.md#the-three-env-layers):** after editing `MAIL_*` in `backend/.env`, run `docker compose up -d --force-recreate php queue` — a plain `restart` keeps the stale values.

## Branding

The Laravel markdown-mail components are published to `backend/resources/views/vendor/mail/` and themed to the app brand:

- `themes/default.css` swaps Laravel's zinc palette for the brand orange (`#EA580C`, matching Vuetify's `primary`) on the CTA button, links, and panel accent.
- `html/header.blade.php` renders the same-origin brand logo (`config('app.url')/apple-touch-icon.png` — a raster PNG for cross-client support, since email clients don't reliably render SVG) inline with the app name.

This themes **every** `MailMessage` (invites, password resets, 2FA codes) at once — so build new notification emails with `MailMessage` (not bespoke HTML) to inherit it.

> Editing these Blade/CSS files needs `php artisan view:clear` and, because the worker renders queued mail, `docker compose restart queue` to take effect.

## Production

Set real SMTP credentials in `backend/.env` and drop Mailpit (it only exists in the dev override).
