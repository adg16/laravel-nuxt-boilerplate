# Activity log (audit trail)

Records **who changed what, when** across the admin surface, via [`spatie/laravel-activitylog`](https://spatie.be/docs/laravel-activitylog) (v5). Complementary to [Blameable](authorization.md#blameable-who-creatededited-a-record), which only stamps the *last* actor. Read-only, gated by `activity.view`.

## Two logging paths

**Model attribute changes are auto-logged.** `User` and `Role` use the `LogsActivity` trait with a `getActivitylogOptions()` (`logOnly([...columns])` + `logOnlyDirty()` + `dontLogEmptyChanges()`), so only meaningful column edits log — and **never** passwords/2FA/avatars (they're not in `logOnly`).

**Pivot & non-model changes are logged explicitly** via `App\Support\ActivityLogger` from the controllers, since they can't be auto-observed:

- user↔role assignments (`UserController`)
- role↔permission grants (`RoleController`)
- settings value changes (`SettingController` — no Eloquent subject at all)

These write `{ old, attributes }` under `withProperties`.

## v5 storage split (important)

The auto-logged diff lands in the **`attribute_changes`** column; manual `withProperties` data lands in **`properties`**. `App\Http\Resources\ActivityResource` **merges both** into one `{ old, attributes }` shape so the frontend renders every row uniformly — **read/search both columns** when touching this.

The **causer** is resolved via `config('activitylog.default_auth_driver') = 'web'` — pinned so it matches the Blameable actor under `statefulApi()` (which otherwise flips the default guard to `sanctum` mid-request).

## The API

`GET /api/activity` (`ActivityController`). Filters: `log_name`, `event`, `subject_type`+`subject_id` (drives the per-record view), `actor` (causer name), `search` (subject name + diff values), and `date_from`/`date_to`.

**Visibility mirrors the user/role lists:** activity whose *subject* is a super-admin/System user or the `Super Admin` role is hidden from non-super-admins (causer names show as-is, like blame stamps).

## The UI

- A global **`/activity-log`** page (paginated `v-data-table-server`, expand-all toggle, per-row diff panel).
- A reusable per-record **`<AppActivityHistory>`** timeline on **`/users/{id}/activity`** and **`/roles/{id}/activity`**, reached via a `mdi-history` row action on the lists.

Diff rendering + action labels are shared through `useActivityFormat` / `<AppActivityDiff>`.

## Scaling & retention

The list is read newest-first, so `created_at` is indexed (added in the activity-log create migration; InnoDB folds in the PK `id` tie-breaker), and the date filter compares the raw column (not the non-sargable `whereDate()`). **History is retained indefinitely — there is no pruning** (an audit trail shouldn't silently drop rows). A fork that wants retention can schedule spatie's `activitylog:clean` (`config('activitylog.clean_after_days')`, 365). `search` is an unindexed `LIKE` scan — fine for occasional admin use; revisit with FULLTEXT only if it becomes a hot path.

## Auditing another model

Add `LogsActivity` + `getActivitylogOptions()` for its columns, and/or call `ActivityLogger` for its relational/non-column changes; extend the frontend `activityLog.*` i18n and (if it's a new subject type) the `subject_type` alias map + visibility rules.
