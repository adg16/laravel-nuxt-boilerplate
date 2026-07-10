import { defineStore } from 'pinia'

// Non-sensitive, UI-shaping config from the backend (GET /api/config) — e.g.
// how the create-user form should behave. Fetched once after auth hydration.
export type UserCreationMode = 'choice' | 'invite' | 'set_password'
export type TwoFactorMode = 'off' | 'optional' | 'required'
export type TwoFactorMethodPolicy = 'totp' | 'email' | 'both'

export const useConfigStore = defineStore('config', () => {
  const api = useApi()
  const userCreationMode = ref<UserCreationMode>('choice')
  const twoFactorMode = ref<TwoFactorMode>('off')
  const twoFactorMethods = ref<TwoFactorMethodPolicy>('totp')

  async function fetch() {
    try {
      const config = await api<{
        userCreationMode: UserCreationMode
        twoFactorMode: TwoFactorMode
        twoFactorMethods: TwoFactorMethodPolicy
      }>('/config')
      userCreationMode.value = config.userCreationMode
      twoFactorMode.value = config.twoFactorMode
      twoFactorMethods.value = config.twoFactorMethods
    } catch {
      // Non-fatal: fall back to the defaults so the create form still works.
    }
  }

  return { userCreationMode, twoFactorMode, twoFactorMethods, fetch }
})
