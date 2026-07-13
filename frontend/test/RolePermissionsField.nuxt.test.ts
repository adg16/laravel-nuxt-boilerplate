import { describe, it, expect } from 'vitest'
import { nextTick } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import RolePermissionsField from '~/components/RolePermissionsField.vue'
import type { Permission } from '~/types/rbac'

// Unordered on purpose (manage before view, users before roles) so the test
// pins the component's deterministic re-ordering, not the input order.
const permissions: Permission[] = [
  { id: 1, name: 'users.manage', roles: [] },
  { id: 2, name: 'users.view', roles: [] },
  { id: 3, name: 'roles.view', roles: [] }
]

describe('RolePermissionsField', () => {
  it('groups permissions by resource and shows a selected/total count per group', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: ['users.view'], permissions }
    })

    // Users group: one of two selected; Roles group: none of one.
    expect(wrapper.text()).toContain('1/2')
    expect(wrapper.text()).toContain('0/1')
  })

  it('selects every permission in a group when its select-all is checked', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: [], permissions }
    })

    const usersSelectAll = wrapper.find('input[aria-label="Select all Users permissions"]')
    await usersSelectAll.setValue(true)

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted!.at(-1)![0]).toEqual(expect.arrayContaining(['users.view', 'users.manage']))
    expect(emitted!.at(-1)![0]).not.toContain('roles.view')
  })

  it('filters permissions by the search query and hides non-matching groups', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: [], permissions }
    })

    const search = wrapper.find('input[type="text"]')
    await search.setValue('manage')

    // Both groups keep their "Manage" permission; "View" is filtered out.
    expect(wrapper.text()).toContain('Manage')
    expect(wrapper.text()).not.toContain('View')

    // A resource-name match surfaces the whole group.
    await search.setValue('users')
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).not.toContain('Roles')
  })

  it('shows an empty state when nothing matches the search', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: [], permissions }
    })

    await wrapper.find('input[type="text"]').setValue('nonexistent')
    expect(wrapper.text()).toContain('No permissions match your search.')
  })

  it('does not crash when the search field is cleared to null', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: [], permissions }
    })

    // Vuetify's `clearable` X button resets the field to null (not ''), so the
    // filter must tolerate a null query rather than calling .trim() on it.
    const field = wrapper.findComponent({ name: 'VTextField' })
    field.vm.$emit('update:modelValue', 'users')
    await nextTick()
    field.vm.$emit('update:modelValue', null)
    await nextTick()

    expect(wrapper.text()).toContain('Roles')
    expect(wrapper.text()).toContain('Users')
  })

  it('clears a fully-selected group when its select-all is unchecked', async () => {
    const wrapper = await mountSuspended(RolePermissionsField, {
      props: { modelValue: ['users.view', 'users.manage', 'roles.view'], permissions }
    })

    const usersSelectAll = wrapper.find('input[aria-label="Select all Users permissions"]')
    await usersSelectAll.setValue(false)

    // Only the untouched Roles permission remains.
    expect(wrapper.emitted('update:modelValue')!.at(-1)![0]).toEqual(['roles.view'])
  })
})
