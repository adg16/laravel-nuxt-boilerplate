import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PasswordInput from '~/components/PasswordInput.vue'

describe('PasswordInput', () => {
  it('masks the value by default and toggles to reveal it', async () => {
    const wrapper = await mountSuspended(PasswordInput)

    expect(wrapper.find('input').attributes('type')).toBe('password')

    const toggle = wrapper.find('button')
    await toggle.trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('text')

    await toggle.trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('password')
  })

  it('exposes the toggle state to assistive tech via aria-pressed', async () => {
    const wrapper = await mountSuspended(PasswordInput)
    const toggle = wrapper.find('button')

    expect(toggle.attributes('aria-pressed')).toBe('false')
    await toggle.trigger('click')
    expect(wrapper.find('button').attributes('aria-pressed')).toBe('true')
  })
})
