import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import Login from '~/pages/login.vue'

// Controllable auth store so the two-step flow is observable without a backend.
// The real router is left intact (mocking useRouter breaks Nuxt internals).
const { loginMock, challengeMock, emailChallengeMock, resendMock } = vi.hoisted(() => ({
  loginMock: vi.fn(),
  challengeMock: vi.fn(),
  emailChallengeMock: vi.fn(),
  resendMock: vi.fn()
}))
// Include the `user`/`fetchUser` the auth.client plugin touches on mount, plus
// the actions the login page calls.
mockNuxtImport('useAuthStore', () => () => ({
  user: null,
  fetchUser: vi.fn(),
  login: loginMock,
  twoFactorChallenge: challengeMock,
  emailChallenge: emailChallengeMock,
  resendEmailChallenge: resendMock
}))

describe('login page validation', () => {
  it('shows field errors when submitting an empty form', async () => {
    const wrapper = await mountSuspended(Login)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Enter a valid email address.')
    expect(wrapper.text()).toContain('Password is required.')
  })

  it('rejects a malformed email', async () => {
    const wrapper = await mountSuspended(Login)

    await wrapper.find('input[type="email"]').setValue('not-an-email')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Enter a valid email address.')
  })
})

describe('login page two-factor challenge', () => {
  beforeEach(() => {
    loginMock.mockReset()
    challengeMock.mockReset()
    emailChallengeMock.mockReset()
  })

  async function submitCredentials(wrapper: Awaited<ReturnType<typeof mountSuspended>>) {
    await wrapper.find('input[type="email"]').setValue('admin@example.com')
    await wrapper.find('input[type="password"]').setValue('password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
  }

  it('routes a TOTP challenge to twoFactorChallenge', async () => {
    loginMock.mockResolvedValue({ twoFactor: true, method: 'totp' })
    challengeMock.mockResolvedValue(undefined)
    const wrapper = await mountSuspended(Login)

    await submitCredentials(wrapper)
    expect(wrapper.text()).toContain('Two-factor authentication')

    await wrapper.find('input[inputmode="numeric"]').setValue('123456')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(challengeMock).toHaveBeenCalledWith({ code: '123456' })
    expect(emailChallengeMock).not.toHaveBeenCalled()
  })

  it('routes an email challenge to emailChallenge', async () => {
    loginMock.mockResolvedValue({ twoFactor: true, method: 'email' })
    emailChallengeMock.mockResolvedValue(undefined)
    const wrapper = await mountSuspended(Login)

    await submitCredentials(wrapper)
    // Email variant shows its own subtitle.
    expect(wrapper.text()).toContain('Enter the code we emailed you.')

    await wrapper.find('input[inputmode="numeric"]').setValue('654321')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(emailChallengeMock).toHaveBeenCalledWith({ code: '654321' })
    expect(challengeMock).not.toHaveBeenCalled()
  })
})
