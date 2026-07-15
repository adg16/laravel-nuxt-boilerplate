import type { Activity } from '~/types/activity'

// Shared presentation helpers for an activity entry, so the global log page and
// the per-record history panel humanize actions and render diffs identically.
export interface ActivityDiffRow {
  field: string
  label: string
  old: string
  new: string
  // Whether each side actually had a value — lets the UI show only the added
  // value on a create, only the removed value on a delete, and old→new on edits.
  hasOld: boolean
  hasNew: boolean
}

export function useActivityFormat() {
  const { t, te } = useI18n()

  // Chip color by change kind (falls back to neutral for anything unexpected).
  function eventColor(activity: Activity): string {
    switch (activity.event) {
      case 'created': return 'success'
      case 'deleted': return 'error'
      case 'updated': return 'info'
      default: return 'grey'
    }
  }

  function eventIcon(activity: Activity): string {
    switch (activity.event) {
      case 'created': return 'mdi-plus-circle-outline'
      case 'deleted': return 'mdi-delete-outline'
      case 'updated': return 'mdi-pencil-outline'
      default: return 'mdi-circle-small'
    }
  }

  // Humanized action label: the specific manual-log descriptions win, then a
  // generic "setting changed", otherwise the plain event word.
  function actionLabel(activity: Activity): string {
    if (activity.description === 'role_assignment') return t('activityLog.description.role_assignment')
    if (activity.description === 'permission_change') return t('activityLog.description.permission_change')
    if (activity.log_name === 'settings') return t('activityLog.description.setting_changed')
    return t(`activityLog.event.${activity.event ?? 'updated'}`)
  }

  // A readable name for a changed field. Setting keys resolve to their settings
  // label; known model columns to activityLog.field.*; anything else is shown raw.
  function fieldLabel(field: string, logName: string): string {
    if (logName === 'settings') {
      const key = `settings.items.${field}.label`
      return te(key) ? t(key) : field
    }
    const key = `activityLog.field.${field}`
    return te(key) ? t(key) : field
  }

  function formatValue(value: unknown, logName: string, field: string): string {
    if (value === null || value === undefined || value === '') return '—'
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—'
    if (typeof value === 'boolean') return value ? t('settings.on') : t('settings.off')
    // Settings enum values have their own localized option labels.
    if (logName === 'settings') {
      const key = `settings.options.${field}.${value}`
      if (te(key)) return t(key)
    }
    return String(value)
  }

  // Whether a raw property value carries anything (used to decide which side of
  // the diff to render).
  function isPresent(value: unknown): boolean {
    if (value === null || value === undefined || value === '') return false
    if (Array.isArray(value)) return value.length > 0
    return true
  }

  // Turn the old/new property maps into aligned rows for a diff table.
  function diffRows(activity: Activity): ActivityDiffRow[] {
    const old = activity.properties.old ?? {}
    const next = activity.properties.attributes ?? {}
    const fields = Array.from(new Set([...Object.keys(old), ...Object.keys(next)]))
    return fields.map(field => ({
      field,
      label: fieldLabel(field, activity.log_name),
      old: formatValue(old[field], activity.log_name, field),
      new: formatValue(next[field], activity.log_name, field),
      hasOld: isPresent(old[field]),
      hasNew: isPresent(next[field])
    }))
  }

  return { eventColor, eventIcon, actionLabel, fieldLabel, formatValue, diffRows }
}
