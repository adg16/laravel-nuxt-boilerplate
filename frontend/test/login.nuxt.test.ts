import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import Login from '~/pages/login.vue'

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
