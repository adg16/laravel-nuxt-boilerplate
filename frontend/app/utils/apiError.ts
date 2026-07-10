// Turns a thrown $fetch/store error into a user-facing message. Centralizes the
// "server message → fallback → generic" pattern that was duplicated across pages,
// and — the reason this exists — gives rate-limited (429) responses a friendly,
// localized line instead of the raw "Too Many Attempts." or, worse, a bare
// "Something went wrong" from a catch block that ignored the error.
//
// Runs from event handlers (outside component setup), so it resolves i18n via
// useNuxtApp().$i18n rather than useI18n().
export function apiErrorMessage(error: unknown, fallback?: string): string {
  const { $i18n } = useNuxtApp()

  const err = error as {
    status?: number
    statusCode?: number
    response?: { status?: number, headers?: { get?: (name: string) => string | null } }
    data?: { message?: string }
  }
  const status = err.status ?? err.statusCode ?? err.response?.status

  if (status === 429) {
    const retryAfter = Number(err.response?.headers?.get?.('retry-after'))
    return retryAfter > 0
      ? $i18n.t('common.tooManyRequestsRetry', { seconds: retryAfter })
      : $i18n.t('common.tooManyRequests')
  }

  return err.data?.message ?? fallback ?? $i18n.t('common.genericError')
}
