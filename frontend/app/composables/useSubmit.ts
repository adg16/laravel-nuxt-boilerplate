// Shared submit state + error handling for the auth forms. Wraps an async
// action with loading tracking and turns a thrown $fetch error into a
// user-facing message (via apiErrorMessage: server message / rate-limit line /
// fallback), so pages don't each re-implement the try/catch/finally dance.
export function useSubmit() {
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
      error.value = apiErrorMessage(e, fallback)
    } finally {
      loading.value = false
    }
  }

  return { loading, error, submit }
}
