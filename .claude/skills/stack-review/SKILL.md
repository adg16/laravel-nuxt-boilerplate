---
name: stack-review
description: Full review of the LOCAL changes in this Laravel + Nuxt boilerplate. Covers everything the native /code-review checks (correctness bugs, removed-behavior, cross-file breakage, simplification, efficiency, altitude, CLAUDE.md conventions) PLUS regression/blast-radius risk to existing features and stack-specific best practices — DRY/reuse, security, performance, exception handling & logging, i18n/translation coverage (no hardcoded strings, keys exist and locale files stay in sync), and idiomatic Laravel, Nuxt/Vue, and Vuetify style (coding style included). Use when the user asks to "review my changes", "best practices review", "quality review", "check DRY/security/performance", "check translations/i18n", "is this idiomatic", or names any of these stacks for review. Trigger on "stack review", "best-practice review", "review for security/performance".
---

# Stack Review

A single-pass review of the **uncommitted local changes** in this repo that combines two things: the correctness-and-cleanup angles of the native `/code-review`, **and** a best-practices/hardening pass tuned to **this** stack — Laravel 13 / PHP 8.5 API + Nuxt 4 SPA (`ssr: false`) + Sanctum + Pinia + Vuetify 4. It answers both "is this correct?" and "is this idiomatic, DRY, secure, fast, and well-styled?"

This is the inline, one-sitting version. For a heavier recall pass on a big or risky diff, `/code-review high` (or `ultra`) runs the same correctness angles across multiple agents with a verify vote — mention that option if the change is large.

Report findings; **do not edit** unless the user asks you to apply them.

## Steps

### 1. Gather scope
Run `git diff HEAD` and list untracked files with `git status --short`; `cat` the untracked ones. Review the diff **plus** the enclosing function/component of each hunk (a change can be idiomatic in isolation but wrong for its surroundings). Ignore files outside the change unless a finding needs their context.

### 2. Run the mechanical checks first
Run `make lint` (Pint `--test` + ESLint) and, for frontend changes, `docker compose exec -T node npm run typecheck`. These catch formatting/style the review shouldn't spend attention on. Note any failures as findings under the relevant style section, then focus your own reading on what the linters *can't* see.

### 3. Review each dimension

Read the changed code against the checklists below. These are prompts, not an exhaustive gate — apply judgment. For every finding, capture `file:line`, a one-line problem statement, **why** it matters for this stack, and a concrete suggested fix (name the exact helper/component/pattern to use).

The first four dimensions are the native `/code-review` angles — run them first; a correctness bug always outranks any best-practice or style finding. The rest are the stack-specific best-practices pass.

**Correctness** (native /code-review: line-by-line + removed-behavior + cross-file)
- Line-by-line: for every changed line, ask what input, state, timing, or platform makes it wrong — inverted/wrong conditions, off-by-one, null/undefined deref, missing `await`, falsy-zero (`0`/`''`) treated as absent, wrong-variable copy-paste, swallowed errors in `catch`, unescaped regex metachars. Read the whole enclosing function — bugs on *unchanged* lines of a touched function are in scope.
- Removed-behavior: for each deleted/replaced line, name the invariant or guard it enforced, then find where the new code re-establishes it. If you can't, that's a finding (dropped validation, removed error path, deleted test covering a real case).
- Cross-file: for each changed function/signature/return-shape/exception, Grep its callers and callees and confirm the change doesn't break a call site (new precondition, changed shape, new throw, ordering/timing dependency).

**Regression risk / blast radius** (does new code break something that already works?)
- List what *else* touches what this change touches, then ask whether each still behaves as before: shared code paths and components (a tweak to `useApi`, an auth store action, or a layout affects every caller); global/shared state (Pinia stores, Sanctum session, Redis logical DBs, `bootstrap/app.php` middleware, service-provider bindings that now run for *all* requests — e.g. `ResetPassword::createUrlUsing`); a route/middleware/global guard change that alters access to unrelated pages; config or cached artifacts (`config:cache`/`route:cache` from the entrypoint may pin old values; `.env` keys promoted to OS env vars); DB migrations/seeders and anything with destructive side effects (a test or command that could mutate the live dev DB).
- Behavioral, not just signature-level: a change can keep every type intact yet still change runtime behavior for an existing feature (order of operations, defaults, when an effect fires, what now renders). Name the specific existing feature at risk and the trigger.
- Coverage check: would the current test suite actually catch this regression? If a realistic break would leave the suite green, that's a finding — call out the gap and suggest the specific test (feature test, or a manual step to run). Prefer verifying the risky path directly (drive it / curl it) over asserting it's fine.

**Simplification** (native /code-review)
- Unnecessary complexity the diff adds: redundant or derivable state, copy-paste with slight variation, deep nesting, dead code left behind. Name the simpler form that does the same job.

**Altitude** (native /code-review)
- Is the change at the right depth, or a fragile bandaid? Special cases layered on shared infrastructure usually mean the fix isn't deep enough — prefer generalizing the underlying mechanism over stacking special cases.

**Conventions — CLAUDE.md** (native /code-review)
- Read every governing `CLAUDE.md` (repo root, user-level `~/.claude/CLAUDE.md`, and any in an ancestor directory of a changed file). Flag only clear violations where you can **quote the exact rule and the exact offending line** — no style preferences, no "spirit of the doc". Cite the CLAUDE.md path in the finding. (This repo's root CLAUDE.md has load-bearing rules: pinned image majors, `opcache` not in `docker-php-ext-install`, Redis logical-DB separation, `env()` only in config, the `resolver` + variable nginx pattern, `srcDir` = `app/`.)

**DRY / reuse**
- New logic that re-implements an existing `app/composables/*`, `app/stores/*`, `app/components/*`, Eloquent scope, API Resource, Form Request, or trait — name the thing to call instead.
- Duplicated validation rules, response shaping, or fetch/CSRF logic that belongs in a Form Request, `UserResource`-style resource, or `useApi`.
- Copy-pasted markup that should be a shared component (e.g. the auth pages sharing field patterns).

**Security**
- Backend: validation via Form Requests (not inline `$request->all()`); mass-assignment surface (`#[Fillable]`/`$fillable`); authorization on state-changing routes; no raw/unbound SQL; password rules via `Password::defaults()`; auth/reset endpoints rate-limited (`throttle`); no user-enumeration leaks; no secrets or tokens in code, logs, or committed `.env`; `env()` only in `config/*` (cached config returns null elsewhere).
- Sanctum/SPA: same-origin cookie flow intact; CSRF token echoed (`X-XSRF-TOKEN`); nothing that would require loosening CORS or `SANCTUM_STATEFUL_DOMAINS`.
- Frontend: no `v-html` with user data (XSS); no secrets in `runtimeConfig.public`; auth-gated routes covered by `middleware/auth.global.ts`; external links `rel="noopener"`.

**Error handling & logging**
- Backend exceptions: no silently swallowed or empty `catch`; no over-broad `catch (\Throwable)` that hides real bugs; catch only what you can handle, otherwise let it bubble to Laravel's handler (`bootstrap/app.php` `withExceptions`, which renders JSON for `api/*`). Prefer typed/domain exceptions and the framework ones already used here (`ValidationException`, `AuthenticationException`) over ad-hoc `response()->json([...], 4xx)`. Operations that can fail externally (mail send, queue job, HTTP call) have a defined failure path, not an unhandled 500.
- Backend logging: use the `Log` facade with structured context (`Log::warning($msg, [...])`), appropriate level (debug/info/warning/error), and a trace for swallowed-but-expected failures so they're diagnosable. **Never log secrets, passwords, raw tokens, or PII** (reset tokens, `X-XSRF-TOKEN`, credentials). Don't log-and-rethrow the same error (double logging). Don't leak stack traces/internal messages to clients — rely on `APP_DEBUG=false` in prod.
- Frontend: every `await` that can throw (`$fetch`/store actions) is inside `try/catch`; the error is surfaced to the user (`v-alert`/`useSnackbar`) rather than swallowed; `loading`/pending state is reset in `finally`; caught errors are narrowed before property access (e.g. `const err = e as { data?: { message?: string } }`) and fall back to a generic message. No leftover `console.log`/`console.error` as the only handling; distinguish expected statuses (401/422) from unexpected ones.

**Performance** (also covers native /code-review's *efficiency* angle)
- Wasted work the diff introduces: redundant computation or repeated I/O, independent async operations awaited sequentially that could run together, blocking work added to a hot path or startup. Also flag long-lived objects/closures that capture a large enclosing scope and keep it alive.
- Eloquent N+1 (missing `with()`/eager loading); queries in loops; `select *` where columns suffice; missing pagination on unbounded lists.
- Redis DB separation respected (cache/session/queue on their own logical DBs — see CLAUDE.md); no accidental cross-use.
- Frontend: redundant API calls (duplicate `$fetch`/store hydration); missing `computed` memoization; wide reactive state; blocking work in `plugins/*.client.ts` startup; large watchers firing on every keystroke.

**Laravel best practices & style**
- Pint `laravel` preset compliance (step 2 covers this — only call out what Pint won't fix).
- Controllers thin: validation in Form Requests, responses via API Resources, non-trivial logic in actions/services, not fat controllers.
- Typed signatures and return types; constructor property promotion; enums/`match` where they fit PHP 8.5; `Str`/`Arr` helpers over hand-rolled loops.
- Route model binding over manual `find`; named config over `env()` outside config; events/notifications over inline side effects where the codebase already does so.

**Nuxt / Vue best practices & style**
- ESLint stylistic config compliance: `commaDangle: 'never'`, `braceStyle: '1tbs'` (step 2 covers mechanical parts).
- `<script setup lang="ts">` + Composition API; rely on auto-imports (no manual `import { ref }`); `definePageMeta` for layout/middleware.
- State: `ref`/`computed`/`defineModel` used correctly; no derivable state stored in a second `ref`; stores for shared state, composables for shared logic, `~`/`app/` structure respected (srcDir is `app/`).
- SPA reality (`ssr: false`): client-only guards where needed; no SSR-only assumptions.
- Typed props/emits/models; no `any` leaking; explicit return types on exported composables.

**Vuetify best practices**
- Prefer `v-*` components and their props over hand-rolled markup/CSS: `v-form` + `v-text-field` with `:rules` for real forms (not bare `<form>` + manual refs when validation matters — validate via a `VForm` ref's `validate()` on submit); `v-btn`/`v-text-field`/`v-alert` props over custom classes. Field rules go through the shared `zodRule` helper so validation messages stay single-sourced with Zod.
- Theming through the `createVuetify` config in `app/plugins/vuetify.ts` (`theme.themes.<name>.colors`) and Vuetify design tokens (`color="primary"`, `text-medium-emphasis`, `rgb(var(--v-theme-primary))`) rather than hard-coded hex/color values in components.
- Component-level styling via component props/variants and Vuetify utility classes (`d-flex`, `ga-4`, `text-caption`), reserving scoped CSS for what the utilities can't express; avoid deep `::v-deep` overrides of Vuetify internals.
- Global feedback (toasts) via the single `<AppSnackbar>` + `useSnackbar().notify`, not ad-hoc `v-snackbar` per page.
- Accessibility: every input has a `label`; icon-only buttons have `aria-label`; interactive controls are keyboard-reachable.

**Internationalization (i18n)**
- **No hardcoded user-facing strings.** Every string a user reads must be a translation key resolved through `$t('…')` (templates) or `t('…')` (`<script setup>`), not a literal. This includes non-obvious surfaces: `:label`/`:title`/`:aria-label`/`:placeholder`... prop bindings, `v-alert` `title`/`text`, `v-btn`/`v-list-item` slot text, Zod messages via `zodRule(z.string().email(t('…')))`, `useSnackbar().notify(t('…'))`, `definePageMeta({ breadcrumb, subtitle })` (those meta values are i18n **keys**, translated in `useBreadcrumbs`), and `nav.ts` `titleKey`. A literal like `label="Email"` or `title="Sign in"` in a changed `.vue`/`.ts` is a finding — flag it and name the key to add. (Genuinely non-linguistic values — icon names, `you@example.com`/`••••••••` placeholders, demo record data — are not strings to translate; use judgment.) Quick sweep for the diff: `git diff HEAD -- 'frontend/**/*.vue' 'frontend/**/*.ts'` then scan added lines for quoted prose in `label=`, `title=`, `text=`, `aria-label=`, `placeholder=`, and Zod `.min()/.email()` messages that aren't `$t`/`t(` calls.
- **Every referenced key exists, and locale files stay in sync.** For each new `$t('x.y')`/`t('x.y')`/`titleKey`/breadcrumb-or-subtitle key, confirm it's present in `frontend/i18n/locales/en.json`. If more than one locale file exists, all of them must define the same key set — a key added to `en.json` but missing from another locale is a finding (that string won't translate). A key referenced in code but absent from `en.json` is a Critical-ish correctness finding (renders the raw key path to the user).
- **i18n API used correctly.** `useI18n()` must be called at the top of a component `setup` — in composables/utilities that can run outside setup (`useApi`, `useSubmit`, plain `utils/*`), use `useNuxtApp().$i18n` instead (calling `useI18n()` there throws `Must be called at the top of a setup function`). New locales added to `nuxt.config.ts` `i18n.locales` need a matching `<code>.json`; new codes in the backend's `supported_locales` need a matching `backend/lang/<code>/` directory.
- **Backend messages localizable.** New user-facing API strings that should localize go through `__()` / translation keys (as the password-reset flow does with `__($status)`), not hardcoded English in the controller — so the `SetLocale` middleware can translate them.

**Documentation**
- **Don't forget the docs.** If the change alters something a significant doc describes, flag the stale doc as a finding and name the exact section to update. Check the root `README.md`, the root `CLAUDE.md` (its Architecture/Commands/gotchas sections are load-bearing), any nested `CLAUDE.md`, and `make` targets / `.env.example` keys referenced in docs. Triggers include: new/changed/removed `make` command or Docker service, new env var or config key, a new architectural pattern or convention (auth/RBAC/settings/i18n flow), a changed setup/bootstrap step, a new dependency, or altered behavior a doc currently documents as working differently. A code change that makes an existing doc statement wrong is a finding; a doc that simply *could* mention the new thing but isn't now inaccurate is at most Low.

### 4. Verify before reporting

Apply the native `/code-review` verify discipline so the report stays high-signal:
- **Dedup** near-duplicates (same defect, same place, same reason → keep one).
- **Every finding needs a nameable failure scenario** — concrete inputs/state → wrong output, crash, duplicated code, wasted work, or the exact rule broken. If you can't name one, drop it.
- **Keep** a finding when the triggering state is realistic (error/cold-cache path, nil on a rare-but-reachable branch, falsy-zero, boundary off-by-one, races, retry/partial-failure). **Drop** it only when it's constructibly wrong: factually contradicted by the code (quote the line), provably impossible (type/constant/invariant), or already handled in this diff (cite the guard). Pure style with no observable effect and anything Pint/ESLint already enforces are not findings.
- For a subtle correctness or security claim, **confirm it against the code** (Read the callers/config/framework method) rather than reporting on suspicion.

### 5. Report

Group findings by **severity**, most severe first — **Critical, High, Medium, Low** — and within each severity list the most impactful finding first. Do **not** group by dimension; the dimension is just a tag on each finding. For each finding give: the severity, the dimension it came from (e.g. `Correctness`, `Security`, `DRY/reuse`, `Vuetify`), `file:line`, the problem, why it matters for this stack, and the concrete fix (name the exact helper/component/pattern).

Severity scale:
- **Critical** — a shipped correctness/security defect that will bite in normal use: data loss/corruption, auth bypass or secret/PII leak, a guaranteed crash on a common path, a broken migration.
- **High** — a correctness/security/perf regression on a realistic-but-not-guaranteed path, or real (not stylistic) duplication that will cause divergence bugs.
- **Medium** — maintainability or idiom problems, blast-radius risks, or missing test coverage for a reachable break.
- **Low** — style nits the linter missed and cosmetic idiom preferences with no observable effect.

A correctness or security finding outranks any style/idiom finding at the same severity when you order within a bucket. If a severity bucket is empty, omit it. If the whole review is clean, say so in one line rather than padding.

End with a one-line verdict and offer to apply the fixes.
