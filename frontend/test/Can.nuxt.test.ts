import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import Can from '~/components/Can.vue'

// Isolate <Can>'s rendering logic from the store by mocking useAuthz.
const { canMock, hasRoleMock } = vi.hoisted(() => ({ canMock: vi.fn(), hasRoleMock: vi.fn() }))
mockNuxtImport('useAuthz', () => () => ({ can: canMock, canAny: vi.fn(), hasRole: hasRoleMock }))

describe('Can', () => {
  beforeEach(() => {
    canMock.mockReset()
    hasRoleMock.mockReset()
  })

  it('renders the slot when the permission is granted', async () => {
    canMock.mockReturnValue(true)
    const wrapper = await mountSuspended(Can, {
      props: { permission: 'users.manage' },
      slots: { default: () => 'SECRET' }
    })

    expect(wrapper.text()).toContain('SECRET')
    expect(canMock).toHaveBeenCalledWith('users.manage')
  })

  it('hides the slot when the permission is denied', async () => {
    canMock.mockReturnValue(false)
    const wrapper = await mountSuspended(Can, {
      props: { permission: 'users.manage' },
      slots: { default: () => 'SECRET' }
    })

    expect(wrapper.text()).not.toContain('SECRET')
  })

  it('requires both permission and role when both are given', async () => {
    canMock.mockReturnValue(true)
    hasRoleMock.mockReturnValue(false)
    const wrapper = await mountSuspended(Can, {
      props: { permission: 'users.manage', role: 'Admin' },
      slots: { default: () => 'SECRET' }
    })

    expect(wrapper.text()).not.toContain('SECRET')
  })
})
