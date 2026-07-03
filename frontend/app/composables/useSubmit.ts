// Shared submit state + error handling for the auth forms. Wraps an async
// action with loading tracking and turns a thrown $fetch error into a
// user-facing message (server-provided message when present, else a fallback),
// so pages don't each re-implement the try/catch/finally dance.
export function useSubmit() {
  const loading = ref(false)
  const error = ref('')

  async function submit(
    action: () => Promise<void>,
    fallback = 'Something went wrong. Please try again.'
  ): Promise<void> {
    error.value = ''
    loading.value = true

    try {
      await action()
    } catch (e) {
      const err = e as { data?: { message?: string } }
      error.value = err.data?.message ?? fallback
    } finally {
      loading.value = false
    }
  }

  return { loading, error, submit }
}
