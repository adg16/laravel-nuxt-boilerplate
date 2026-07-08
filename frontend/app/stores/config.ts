import { defineStore } from 'pinia'

// Non-sensitive, UI-shaping config from the backend (GET /api/config) — e.g.
// how the create-user form should behave. Fetched once after auth hydration.
export type UserCreationMode = 'choice' | 'invite' | 'set_password'

export const useConfigStore = defineStore('config', () => {
  const api = useApi()
  const userCreationMode = ref<UserCreationMode>('choice')

  async function fetch() {
    try {
      const config = await api<{ userCreationMode: UserCreationMode }>('/config')
      userCreationMode.value = config.userCreationMode
    } catch {
      // Non-fatal: fall back to the default so the create form still works.
    }
  }

  return { userCreationMode, fetch }
})
