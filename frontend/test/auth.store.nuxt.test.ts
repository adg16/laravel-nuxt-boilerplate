import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mockNuxtImport, registerEndpoint } from '@nuxt/test-utils/runtime'
import { setActivePinia, createPinia } from 'pinia'
import type { User } from '~/types/user'

// Replace useApi() with a controllable mock so the store's HTTP calls are
// observable without a real backend. useApi() returns the api fetcher, so the
// mock is `() => apiMock`.
const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }))
mockNuxtImport('useApi', () => () => apiMock)

// getCsrfCookie() hits this before every mutating call.
registerEndpoint('/sanctum/csrf-cookie', () => ({}))

const fakeUser: User = {
  id: 1,
  name: 'Admin',
  email: 'admin@example.com',
  roles: ['Super Admin'],
  permissions: [],
  is_protected: true,
  is_verified: true,
  two_factor_enabled: false,
  created_at: '2026-01-01T00:00:00.000000Z'
}

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.mockReset()
  })

  it('login posts credentials (after the CSRF cookie) and stores the user', async () => {
    apiMock.mockResolvedValueOnce(fakeUser)
    const auth = useAuthStore()

    const result = await auth.login({ email: 'admin@example.com', password: 'password' })

    expect(apiMock).toHaveBeenCalledWith('/login', {
      method: 'POST',
      body: { email: 'admin@example.com', password: 'password' }
    })
    expect(result).toEqual({ twoFactor: false, method: 'totp' })
    expect(auth.user).toEqual(fakeUser)
  })

  it('login signals a 2FA challenge and does not set the user', async () => {
    apiMock.mockResolvedValueOnce({ two_factor: true, two_factor_method: 'email' })
    const auth = useAuthStore()

    const result = await auth.login({ email: 'admin@example.com', password: 'password' })

    expect(result).toEqual({ twoFactor: true, method: 'email' })
    expect(auth.user).toBeNull()
  })

  it('twoFactorChallenge posts the code and stores the user', async () => {
    apiMock.mockResolvedValueOnce(fakeUser)
    const auth = useAuthStore()

    await auth.twoFactorChallenge({ code: '123456' })

    expect(apiMock).toHaveBeenCalledWith('/two-factor-challenge', {
      method: 'POST',
      body: { code: '123456' }
    })
    expect(auth.user).toEqual(fakeUser)
  })

  it('logout clears the user', async () => {
    apiMock.mockResolvedValueOnce({ message: 'Logged out.' })
    const auth = useAuthStore()
    auth.user = fakeUser

    await auth.logout()

    expect(apiMock).toHaveBeenCalledWith('/logout', { method: 'POST' })
    expect(auth.user).toBeNull()
  })

  it('forgotPassword returns the server message', async () => {
    apiMock.mockResolvedValueOnce({ message: 'If that email address is in our system…' })
    const auth = useAuthStore()

    const message = await auth.forgotPassword('admin@example.com')

    expect(message).toBe('If that email address is in our system…')
  })

  it('resetPassword returns the server message', async () => {
    apiMock.mockResolvedValueOnce({ message: 'Your password has been reset.' })
    const auth = useAuthStore()

    const message = await auth.resetPassword({
      token: 'tok',
      email: 'admin@example.com',
      password: 'new-password',
      password_confirmation: 'new-password'
    })

    expect(message).toBe('Your password has been reset.')
  })
})
