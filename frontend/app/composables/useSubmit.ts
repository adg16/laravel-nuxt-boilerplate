// Shared submit state + error handling for the auth/admin forms. Wraps an async
// action with loading tracking and turns a thrown $fetch error into a
// user-facing `error` message (via apiErrorMessage: server message / rate-limit
// line / fallback) — so pages don't each re-implement the try/catch/finally
// dance. For Laravel 422s it ADDITIONALLY exposes a per-field `fieldErrors` map,
// so a form can render each message inline under its field (bind fieldErrors[key]
// to :error-messages) exactly like a client-side rule; `error` is still set, so
// consumers that don't bind fieldErrors keep showing the message in their alert.
export function useSubmit() {
  const loading = ref(false)
  const error = ref('')
  // Laravel's 422 `errors` bag, keyed by field name → messages. Bind an entry
  // to a field's `:error-messages` to render it inline like a client-side rule.
  const fieldErrors = ref<Record<string, string[]>>({})

  // True while a 422 owns any field. Key-presence (not non-empty) so it stays
  // true after clearFieldError empties a field to [], which lets a form hide its
  // redundant summary alert without it flashing back as the user fixes a field.
  const hasFieldErrors = computed(() => Object.keys(fieldErrors.value).length > 0)

  // Drop a field's stale server error the moment the user edits it (empty
  // messages read as "no error" to Vuetify), keeping the key so a
  // key-presence check stays stable. Called with no field, clear them all —
  // e.g. when reopening a form dialog.
  function clearFieldError(field?: string): void {
    if (field === undefined) {
      fieldErrors.value = {}
      return
    }
    fieldErrors.value[field] = []
  }

  async function submit(
    action: () => Promise<void>,
    fallback?: string
  ): Promise<void> {
    error.value = ''
    fieldErrors.value = {}
    loading.value = true

    try {
      await action()
    } catch (e) {
      // Laravel validation (422) → also route each message to its field so a
      // form can render it inline. `error` is ALWAYS set too: it's the only
      // error channel for consumers that don't bind fieldErrors (auth pages),
      // and it lets a form surface any 422 whose key has no matching field.
      // Forms that render inline suppress their own redundant summary alert.
      const errors = (e as { data?: { errors?: Record<string, string[]> } }).data?.errors
      if (errors && Object.keys(errors).length > 0) {
        fieldErrors.value = errors
      }
      error.value = apiErrorMessage(e, fallback)
    } finally {
      loading.value = false
    }
  }

  return { loading, error, fieldErrors, hasFieldErrors, clearFieldError, submit }
}
