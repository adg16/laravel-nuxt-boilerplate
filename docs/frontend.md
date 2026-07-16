# Frontend

A Nuxt 4 SPA (`ssr: false`) with Pinia and Vuetify 4. See also [internationalization.md](internationalization.md).

## Structure

Nuxt 4's `srcDir` defaults to **`frontend/app/`** — composables, stores, middleware, plugins, layouts, pages, and types all live under `app/`, not at the frontend root (this matters for `@pinia/nuxt`'s `storesDirs` resolution and the `~` import alias).

- `middleware/auth.global.ts` — global route guard (auth + permission + 2FA-enrollment redirects).
- `plugins/auth.client.ts` — hydrates the auth store on app init, before route resolution.
- `i18n/locales/*.json` — messages (at the frontend root, not under `app/`).

## The layout shell

The `default` layout owns the page content shell: it wraps the routed page in a padded `v-container` and renders the `<AppBreadcrumbs>` header (the title comes from each page's `definePageMeta({ breadcrumb })` — an i18n **key**).

So pages under this layout render **bare body content** (a plain root `<div>`, no `v-container`/padding, no `<h1>`). Page intro rows (description + right-aligned actions) go through `<AppPageHeader>`. Custom `definePageMeta` keys are typed in `app/types/page-meta.d.ts`.

## Vuetify 4 (no Nuxt UI, no Tailwind)

Vuetify is wired **manually** (there's no `vuetify-nuxt-module` — it doesn't reliably support Vuetify 4 yet): `nuxt.config.ts` registers `vite-plugin-vuetify`, and `app/plugins/vuetify.ts` calls `createVuetify`.

- **That plugin is the single source** for the theme (brand `primary`/`secondary` per light/dark) and app-wide component defaults (e.g. `variant: 'outlined'` on inputs) — set colors/defaults there, not per-component or with raw hex.
- **No Tailwind** — style with Vuetify utility classes (`d-flex`, `ga-4`, `text-medium-emphasis`) and theme tokens (`color="primary"`, `rgb(var(--v-theme-primary))`), reserving scoped CSS for what utilities can't express.
- **This is Vuetify 4, not 3** — an unknown utility class fails **silently**. Notably, typography uses the Material Design 3 scale `text-{display|headline|title|body|label}-{large|medium|small}`; the v3 names (`text-h4`, `text-body-1`, …) **do not exist**. Don't copy v3 snippets.

## Forms & validation

Forms use `v-form` + `v-text-field` with `:rules`. Rules come from **Zod schemas** via the `zodRule` helper (`app/utils/validation.ts`) so messages stay single-sourced with the schema, and are validated on submit through a `VForm` ref's `validate()`.

## Shared components

Reuse these rather than hand-rolling equivalents:

| Component | Purpose |
|---|---|
| `<AppDialogShell>` | Modal chrome (accent line, icon, Cancel + primary-action row) |
| `<AppFormDialog>` | Create/edit form dialog (wraps `AppDialogShell`) |
| `<AppConfirmDialog>` | Confirmation dialog (`type` drives accent color/icon) |
| `<AppSnackbar>` + `useSnackbar().notify` | The single global toast — don't add per-page `v-snackbar`s |
| `<AppUserAvatar>` | Avatar image, or initials fallback |
| `<AppPageHeader>` / `<AppBreadcrumbTrail>` | Page intro row / breadcrumb header |
| `<AppSearchPanel>` / `<AppDateRangeField>` / `<AppTableAction>` | List filtering + table row actions |
| `<AppActivityHistory>` / `<AppActivityDiff>` | [Activity-log](activity-log.md) timeline + diff rendering |
| `<AppLanguageSwitcher>` | Locale picker (hidden until a 2nd locale exists) |

> Button labels and dialog titles are auto **Title-Cased in English only** via a `:lang(en)` CSS rule — write i18n strings in natural case and let the CSS handle presentation.

## Pages

`/` (dashboard), `/login`, `/forgot-password`, `/reset-password`, `/accept-invite`, `/profile`, `/security`, `/users`, `/users/{id}/activity`, `/roles` (+ `/new`, `/{id}`, `/{id}/activity`), `/settings`, `/activity-log`.
