import type { BlameStamp } from './rbac'

// Shapes returned by the activity-log endpoint (ActivityResource). Responses are
// unwrapped (no `data` key) — see the backend's JsonResource::withoutWrapping().

// Which registered log an entry belongs to — drives the log-name filter/label.
export type ActivityLogName = 'users' | 'roles' | 'settings'

// The change kind spatie records. Manual (pivot/settings) logs use 'updated'.
export type ActivityEvent = 'created' | 'updated' | 'deleted'

// The record an activity is about. Null for non-model events (e.g. a settings
// change). `type` is a short alias; `label` is the subject's display name, and
// falls back to the diff'd attributes when the record itself was deleted.
export interface ActivitySubject {
  type: 'user' | 'role' | string
  id: number | string | null
  label: string | null
}

// A field diff. Keys are field names (e.g. `name`, `roles`, or a setting key);
// values are whatever changed (scalars or string lists). Either side may be null.
export type ActivityChangeSet = Record<string, unknown> | null

export interface Activity {
  id: number
  log_name: ActivityLogName | string
  event: ActivityEvent | null
  // The raw description spatie stored (e.g. 'role_assignment', a setting key, or
  // the default event word) — the frontend humanizes it for display.
  description: string
  subject: ActivitySubject | null
  // The acting user, or null when the actor was later deleted / there was none.
  causer: BlameStamp | null
  properties: {
    old: ActivityChangeSet
    attributes: ActivityChangeSet
  }
  created_at: string
}
