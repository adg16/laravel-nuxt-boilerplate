// Shared submit state + error handling for the auth forms. Wraps an async
// action with loading tracking and turns a thrown $fetch error into a
// user-facing message (server-provided message when present, else a fallback),
// so pages don't each re-implement the try/catch/finally dance.
export function useSubmit() {
  // The app's global i18n instance (rather than useI18n(), which must run at the
  // top of a component setup) so the generic-error fallback resolves wherever
  // submit() is used.
  const { $i18n } = useNuxtApp()
  const loading = ref(false)
  const error = ref('')

  async function submit(
    action: () => Promise<void>,
    fallback?: string
  ): Promise<void> {
    error.value = ''
    loading.value = true

    try {
      await action()
    } catch (e) {
      const err = e as { data?: { message?: string } }
      error.value = err.data?.message ?? fallback ?? $i18n.t('common.genericError')
    } finally {
      loading.value = false
    }
  }

  return { loading, error, submit }
}
