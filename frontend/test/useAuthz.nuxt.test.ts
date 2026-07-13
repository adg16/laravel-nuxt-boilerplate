import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { setActivePinia, createPinia } from 'pinia'
import type { User } from '~/types/user'

// The store instantiates useApi() on setup; stub it so no real HTTP happens.
const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }))
mockNuxtImport('useApi', () => () => apiMock)

function userWith(roles: string[], permissions: string[]): User {
  return { id: 1, name: 'A', email: 'a@example.com', roles, permissions, is_protected: false, is_verified: true, two_factor_enabled: false, created_at: '' }
}

describe('useAuthz', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('grants a super-admin every permission regardless of explicit grants', () => {
    const auth = useAuthStore()
    auth.user = userWith(['Super Admin'], [])
    const { can, hasRole } = useAuthz()

    expect(can('users.manage')).toBe(true)
    expect(can('anything.at.all')).toBe(true)
    expect(hasRole('Super Admin')).toBe(true)
  })

  it('limits a normal user to their explicit permissions', () => {
    const auth = useAuthStore()
    auth.user = userWith(['Viewer'], ['users.view'])
    const { can, canAny, hasRole } = useAuthz()

    expect(can('users.view')).toBe(true)
    expect(can('users.manage')).toBe(false)
    expect(canAny(['users.manage', 'users.view'])).toBe(true)
    expect(canAny(['users.manage', 'roles.manage'])).toBe(false)
    expect(hasRole('Viewer')).toBe(true)
    expect(hasRole('Admin')).toBe(false)
  })

  it('denies everything when there is no user', () => {
    const { can, hasRole } = useAuthz()

    expect(can('users.view')).toBe(false)
    expect(hasRole('Super Admin')).toBe(false)
  })
})
