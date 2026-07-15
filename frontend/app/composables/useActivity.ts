import type { Activity } from '~/types/activity'

// Thin wrapper over the read-only audit-trail endpoint, mirroring useUsers.
// Server-side pagination/sort/filter params for the activity list.
export interface ActivityListParams {
  page: number
  perPage: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  logName?: string[]
  event?: string[]
  // Subject filter — drives the per-record history panel.
  subjectType?: 'user' | 'role'
  subjectId?: number
  // Filter by the acting user's name.
  actor?: string
  // Content search across subject name and the change values.
  search?: string
  // Inclusive date range (yyyy-mm-dd) on when the activity was recorded.
  dateFrom?: string | null
  dateTo?: string | null
}

export interface ActivityListResult {
  data: Activity[]
  total: number
}

export function useActivity() {
  const api = useApi()

  return {
    list: (params: ActivityListParams) => api<ActivityListResult>('/activity', {
      query: {
        page: params.page,
        per_page: params.perPage,
        sort_by: params.sortBy || undefined,
        sort_dir: params.sortDir || undefined,
        // Comma-joined so they survive query serialization as a single value the
        // backend splits (repeated keys don't round-trip to a PHP array).
        log_name: params.logName?.length ? params.logName.join(',') : undefined,
        event: params.event?.length ? params.event.join(',') : undefined,
        subject_type: params.subjectType || undefined,
        subject_id: params.subjectId || undefined,
        actor: params.actor?.trim() || undefined,
        search: params.search?.trim() || undefined,
        date_from: params.dateFrom || undefined,
        date_to: params.dateTo || undefined,
        // Send the browser timezone only with a date filter, so the backend maps
        // the picked local day to the right instant range.
        tz: (params.dateFrom || params.dateTo) ? Intl.DateTimeFormat().resolvedOptions().timeZone : undefined
      }
    })
  }
}
