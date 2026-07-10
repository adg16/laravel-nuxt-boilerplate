import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import type { RouteLocationNormalized } from 'vue-router'
import type { User } from '~/types/user'
import middleware from '~/middleware/auth.global'

// Controllable stand-ins for the store state + navigateTo the guard reads.
const { userRef, twoFactorModeRef, navigateToMock, canMock } = vi.hoisted(() => ({
  userRef: { value: null as User | null },
  twoFactorModeRef: { value: 'off' as 'off' | 'optional' | 'required' },
  navigateToMock: vi.fn((to: string) => to),
  canMock: vi.fn(() => true)
}))

mockNuxtImport('useAuthStore', () => () => ({ user: userRef.value }))
mockNuxtImport('useConfigStore', () => () => ({ twoFactorMode: twoFactorModeRef.value }))
mockNuxtImport('useAuthz', () => () => ({ can: canMock, canAny: vi.fn(), hasRole: vi.fn() }))
mockNuxtImport('navigateTo', () => navigateToMock)

const user = (over: Partial<User> = {}): User => ({
  id: 1, name: 'A', email: 'a@example.com', roles: [], permissions: [],
  is_protected: false, is_verified: true, two_factor_enabled: false, created_at: '', ...over
})

const route = (path: string): RouteLocationNormalized =>
  ({ path, meta: {} }) as RouteLocationNormalized

describe('auth.global — required-mode 2FA gate', () => {
  beforeEach(() => {
    navigateToMock.mockClear()
    userRef.value = null
    twoFactorModeRef.value = 'off'
  })

  it('redirects an unenrolled user to /security when 2FA is required', () => {
    userRef.value = user({ two_factor_enabled: false })
    twoFactorModeRef.value = 'required'

    middleware(route('/users'), route('/'))

    expect(navigateToMock).toHaveBeenCalledWith('/security')
  })

  it('does not redirect when already on /security', () => {
    userRef.value = user({ two_factor_enabled: false })
    twoFactorModeRef.value = 'required'

    middleware(route('/security'), route('/'))

    expect(navigateToMock).not.toHaveBeenCalled()
  })

  it('does not redirect an enrolled user', () => {
    userRef.value = user({ two_factor_enabled: true })
    twoFactorModeRef.value = 'required'

    middleware(route('/users'), route('/'))

    expect(navigateToMock).not.toHaveBeenCalled()
  })

  it('does not redirect when 2FA is only optional', () => {
    userRef.value = user({ two_factor_enabled: false })
    twoFactorModeRef.value = 'optional'

    middleware(route('/users'), route('/'))

    expect(navigateToMock).not.toHaveBeenCalled()
  })
})
