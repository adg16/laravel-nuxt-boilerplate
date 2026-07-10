import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import Security from '~/pages/security.vue'
import type { User } from '~/types/user'

// Control the auth user + config policy; stub the 2FA API and snackbar so the
// page renders without a backend.
const { userState, methodsState, fetchUserMock, tf } = vi.hoisted(() => ({
  userState: { value: null as User | null },
  methodsState: { value: 'totp' as 'totp' | 'email' | 'both' },
  fetchUserMock: vi.fn(),
  tf: {
    enable: vi.fn(), qrCode: vi.fn(), secretKey: vi.fn(), confirm: vi.fn(),
    enableEmail: vi.fn(), confirmEmail: vi.fn(), resendEmailEnroll: vi.fn(),
    disable: vi.fn(), recoveryCodes: vi.fn(), regenerateRecoveryCodes: vi.fn()
  }
}))

mockNuxtImport('useAuthStore', () => () => ({ user: userState.value, fetchUser: fetchUserMock }))
mockNuxtImport('useConfigStore', () => () => ({
  twoFactorMode: 'optional',
  twoFactorMethods: methodsState.value,
  fetch: vi.fn()
}))
mockNuxtImport('useTwoFactor', () => () => tf)
mockNuxtImport('useSnackbar', () => () => ({ notify: vi.fn() }))

const enabledUser = (method: 'totp' | 'email'): User => ({
  id: 1, name: 'A', email: 'a@example.com', roles: [], permissions: [],
  is_protected: false, is_verified: true,
  two_factor_enabled: true, two_factor_method: method, created_at: ''
})

const disabledUser = (): User => ({
  id: 1, name: 'A', email: 'a@example.com', roles: [], permissions: [],
  is_protected: false, is_verified: true,
  two_factor_enabled: false, two_factor_method: null, created_at: ''
})

describe('security page — change method', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    userState.value = enabledUser('totp')
    methodsState.value = 'totp'
  })

  it('offers a switch to the other method when the policy allows both', async () => {
    methodsState.value = 'both'
    userState.value = enabledUser('totp')
    const wrapper = await mountSuspended(Security)

    // TOTP user → offered a switch to Email.
    expect(wrapper.text()).toContain('Switch to Email')
  })

  it('hides the change-method option when the policy is a single method', async () => {
    methodsState.value = 'totp'
    userState.value = enabledUser('totp')
    const wrapper = await mountSuspended(Security)

    expect(wrapper.text()).not.toContain('Switch to')
  })

  it('re-syncs the user when enrollment fails after the secret was minted', async () => {
    // Enable succeeds (server-side teardown/mint happened) but the follow-up QR
    // fetch fails — the page must re-fetch the user so the UI can't keep showing
    // a stale "enabled" state that no longer matches the backend.
    methodsState.value = 'both'
    userState.value = disabledUser()
    tf.enable.mockResolvedValue(undefined)
    tf.qrCode.mockRejectedValue(new Error('network'))
    const wrapper = await mountSuspended(Security)

    const enableBtn = wrapper.findAll('button').find(b => b.text().includes('Enable two-factor'))
    await enableBtn!.trigger('click')
    await flushPromises()

    expect(tf.enable).toHaveBeenCalled()
    expect(fetchUserMock).toHaveBeenCalled()
  })
})
