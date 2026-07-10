import { defineStore } from 'pinia'
import type { User, TwoFactorMethod } from '~/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const api = useApi()

  async function getCsrfCookie() {
    await $fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  }

  async function fetchUser() {
    try {
      user.value = await api<User>('/user')
    } catch {
      user.value = null
    }
  }

  // Resolves to `{ twoFactor: true, method }` when the account has 2FA enabled:
  // the API returns `{ two_factor: true, two_factor_method? }` (not the user) and
  // the caller must complete the matching challenge. Otherwise the session is
  // established and the user set. `method` defaults to 'totp' (Fortify's TOTP
  // response omits it).
  async function login(credentials: { email: string, password: string }): Promise<{ twoFactor: boolean, method: TwoFactorMethod }> {
    await getCsrfCookie()
    const result = await api<User | { two_factor: true, two_factor_method?: TwoFactorMethod }>(
      '/login', { method: 'POST', body: credentials }
    )

    if (result && 'two_factor' in result) {
      return { twoFactor: true, method: result.two_factor_method ?? 'totp' }
    }

    user.value = result
    await useConfigStore().fetch()
    return { twoFactor: false, method: 'totp' }
  }

  // Second step of a TOTP login: submit a TOTP `code` or a `recovery_code`.
  async function twoFactorChallenge(payload: { code?: string, recovery_code?: string }) {
    user.value = await api<User>('/two-factor-challenge', { method: 'POST', body: payload })
    await useConfigStore().fetch()
  }

  // Second step of an email login: submit the emailed `code` or a `recovery_code`.
  async function emailChallenge(payload: { code?: string, recovery_code?: string }) {
    user.value = await api<User>('/two-factor-email-challenge', { method: 'POST', body: payload })
    await useConfigStore().fetch()
  }

  // Request a fresh emailed login code.
  async function resendEmailChallenge(): Promise<string> {
    const { message } = await api<{ message: string }>('/two-factor-email-challenge/resend', { method: 'POST' })
    return message
  }

  async function register(payload: { name: string, email: string, password: string, password_confirmation: string }) {
    await getCsrfCookie()
    user.value = await api<User>('/register', { method: 'POST', body: payload })
  }

  async function logout() {
    await api('/logout', { method: 'POST' })
    user.value = null
  }

  async function forgotPassword(email: string): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/forgot-password', {
      method: 'POST',
      body: { email }
    })
    return message
  }

  async function resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/reset-password', {
      method: 'POST',
      body: payload
    })
    return message
  }

  async function acceptInvitation(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/accept-invitation', {
      method: 'POST',
      body: payload
    })
    return message
  }

  return { user, getCsrfCookie, fetchUser, login, twoFactorChallenge, emailChallenge, resendEmailChallenge, register, logout, forgotPassword, resetPassword, acceptInvitation }
})
