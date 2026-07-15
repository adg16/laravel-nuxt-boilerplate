import { describe, it, expect } from 'vitest'
import { nextTick } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { VDatePicker } from 'vuetify/components'
import AppDateRangeField from '~/components/AppDateRangeField.vue'

// Guards the string↔Date conversion and the range picker round-trip — the most
// bug-prone part (a UTC slip would shift a boundary by a day, which no other
// test would catch).
describe('AppDateRangeField', () => {
  const inputValue = (wrapper: { find: (s: string) => { element: Element } }) =>
    (wrapper.find('input').element as HTMLInputElement).value

  it('formats a selected range in the field', async () => {
    const wrapper = await mountSuspended(AppDateRangeField, {
      props: { from: '2026-07-01', to: '2026-07-15' }
    })
    const value = inputValue(wrapper)
    expect(value).toContain('Jul 1')
    expect(value).toContain('Jul 15')
    expect(value).toContain('–') // range separator
  })

  it('shows a single date (no separator) when from equals to', async () => {
    const wrapper = await mountSuspended(AppDateRangeField, {
      props: { from: '2026-07-09', to: '2026-07-09' }
    })
    const value = inputValue(wrapper)
    expect(value).toContain('Jul 9')
    expect(value).not.toContain('–')
  })

  it('is empty when no range is set', async () => {
    const wrapper = await mountSuspended(AppDateRangeField, {
      props: { from: null, to: null }
    })
    expect(inputValue(wrapper)).toBe('')
  })

  it('emits sorted yyyy-mm-dd endpoints when the picker selects a range', async () => {
    // Stub the menu (render its content inline) and the picker (an emittable
    // placeholder) — Vuetify's real overlay needs `visualViewport`, which the
    // test env lacks. This isolates our string↔Date setter, which is the point.
    const wrapper = await mountSuspended(AppDateRangeField, {
      props: { from: null, to: null },
      global: {
        stubs: {
          VMenu: { template: '<div><slot /></div>' },
          VDatePicker: { name: 'VDatePicker', template: '<div />' }
        }
      }
    })
    await nextTick()

    // Range mode yields a Date[]; pass it unsorted to verify normalization.
    wrapper.findComponent(VDatePicker).vm.$emit('update:modelValue', [
      new Date(2026, 6, 20),
      new Date(2026, 6, 12)
    ])
    await nextTick()

    expect(wrapper.emitted('update:from')?.at(-1)).toEqual(['2026-07-12'])
    expect(wrapper.emitted('update:to')?.at(-1)).toEqual(['2026-07-20'])
  })
})
