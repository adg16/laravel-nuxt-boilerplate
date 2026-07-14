// Intl.DateTimeFormat construction is comparatively expensive and formatDateTime
// is called once per timestamp cell on every table render, so cache one formatter
// per locale rather than rebuilding it each call.
const formatters = new Map<string, Intl.DateTimeFormat>()

function formatterFor(locale?: string): Intl.DateTimeFormat {
  const key = locale ?? ''
  let formatter = formatters.get(key)
  if (!formatter) {
    formatter = new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' })
    formatters.set(key, formatter)
  }
  return formatter
}

// Formats an ISO timestamp for display (e.g. in tables) as a localized
// medium-date + short-time string, or an em dash for empty/invalid values.
// Single-sourced so timestamps read consistently wherever they're rendered.
// Pass the active i18n locale so the output follows the user's language.
export function formatDateTime(value?: string | null, locale?: string): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return formatterFor(locale).format(date)
}
