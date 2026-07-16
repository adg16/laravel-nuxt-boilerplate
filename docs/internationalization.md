# Internationalization (i18n)

Both tiers are localized, and the project ships with **English (`en`) only** — the wiring is in place so adding a language is a drop-in.

## Frontend

`@nuxtjs/i18n` with `strategy: 'no_prefix'` — one URL per page, no `/en` `/fr` segments (right for an internal SPA). The active locale is detected from the browser once and persisted in the `i18n_locale` cookie. Messages live in `frontend/i18n/locales/*.json` (at the frontend root — the module's `restructureDir` defaults to `i18n/`).

**Every user-facing string is a translation key — no hardcoded literals.** This includes non-obvious surfaces: Zod validation messages (`zodRule(z.string().email(t('validation.email')))`), aria-labels, `:label`/`:placeholder`/`:title` props, `useSnackbar().notify(t(...))`, `definePageMeta({ breadcrumb })` (an i18n **key**, translated in `useBreadcrumbs`), and `nav.ts` `titleKey`.

- In templates: `$t('key')`. In `<script setup>`: `useI18n()`'s `t`.
- In composables/utilities that may run **outside** a component setup (`useApi`, `useSubmit`, `utils/*`): use `useNuxtApp().$i18n` — `useI18n()` throws unless called at the top of a setup.

The `<AppLanguageSwitcher>` in the app bar lists the configured locales and only renders once there's more than one (so it stays hidden in the default English-only setup).

## Backend

`App\Http\Middleware\SetLocale` (appended to the `api` group) reads `Accept-Language` — sent by the SPA's `useApi` as the active locale — and sets the app locale to the best match within `config('app.supported_locales')` (falling back to the first entry). So API responses (`__()` validation/auth/password-reset messages) localize too. Laravel's translation files are published under `backend/lang/<code>/`.

New user-facing API strings that should localize go through `__()` / translation keys (as the password-reset flow does with `__($status)`), not hardcoded English in the controller.

## Adding a locale (e.g. `fr`)

1. `frontend/i18n/locales/fr.json` — copy `en.json`, translate the values.
2. Add `{ code: 'fr', name: 'Français', language: 'fr-FR', file: 'fr.json' }` to `i18n.locales` in `frontend/nuxt.config.ts`.
3. Add `'fr'` to `supported_locales` in `backend/config/app.php`.
4. `cp -r backend/lang/en backend/lang/fr` and translate.

The switcher then appears automatically.

> All locale files must define the **same key set** — a key added to `en.json` but missing from another locale won't translate. The `stack-review` skill flags hardcoded literals and missing keys.
