import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import AppActivityHistory from '~/components/AppActivityHistory.vue'

// Isolate the panel from the API + authz: stub the list call and the permission.
const { listMock, canMock } = vi.hoisted(() => ({ listMock: vi.fn(), canMock: vi.fn() }))
mockNuxtImport('useActivity', () => () => ({ list: listMock }))
mockNuxtImport('useAuthz', () => () => ({ can: canMock, canAny: vi.fn(), hasRole: vi.fn() }))

describe('AppActivityHistory', () => {
  beforeEach(() => {
    listMock.mockReset()
    canMock.mockReset()
    canMock.mockReturnValue(true)
  })

  it('fetches the subject history and renders an entry', async () => {
    listMock.mockResolvedValue({
      data: [{
        id: 1,
        log_name: 'roles',
        event: 'updated',
        description: 'permission_change',
        subject: { type: 'role', id: 5, label: 'Editors' },
        causer: { id: 2, name: 'Jane Admin' },
        properties: {
          old: { permissions: ['users.view'] },
          attributes: { permissions: ['users.view', 'users.manage'] }
        },
        created_at: '2026-07-14T10:00:00Z'
      }],
      total: 1
    })

    const wrapper = await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'role', subjectId: 5 }
    })
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith(expect.objectContaining({ subjectType: 'role', subjectId: 5 }))
    expect(wrapper.text()).toContain('Jane Admin')
    expect(wrapper.text()).toContain('Permissions changed')
  })

  it('shows an empty state when there is no history', async () => {
    listMock.mockResolvedValue({ data: [], total: 0 })

    const wrapper = await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'user', subjectId: 9 }
    })
    await flushPromises()

    expect(wrapper.text()).toContain('No activity recorded yet.')
  })

  it('does not fetch when the user lacks activity.view', async () => {
    canMock.mockReturnValue(false)

    await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'user', subjectId: 9 }
    })
    await flushPromises()

    expect(listMock).not.toHaveBeenCalled()
  })

  it('shows the pager and fetches another page when the total exceeds one page', async () => {
    const row = {
      id: 1,
      log_name: 'users',
      event: 'updated',
      description: 'updated',
      subject: { type: 'user', id: 9, label: 'Jane' },
      causer: null,
      properties: { old: { name: 'A' }, attributes: { name: 'B' } },
      created_at: '2026-07-14T10:00:00Z'
    }
    // 25 total with the default page size of 10 → 3 pages.
    listMock.mockResolvedValue({ data: [row], total: 25 })

    const wrapper = await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'user', subjectId: 9 }
    })
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith(expect.objectContaining({ page: 1, perPage: 10 }))
    expect(wrapper.find('.v-pagination').exists()).toBe(true)

    // Jump to page 2 via the pager and confirm a fresh fetch for that page.
    const next = wrapper.find('.v-pagination__next button')
    await next.trigger('click')
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith(expect.objectContaining({ page: 2, perPage: 10 }))
  })

  it('refetches with the content search when the user types', async () => {
    listMock.mockResolvedValue({ data: [], total: 0 })

    const wrapper = await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'user', subjectId: 9 }
    })
    await flushPromises()
    listMock.mockClear()

    // Target the search field by its placeholder (there are several text inputs).
    await wrapper.find('input[placeholder="By changed value"]').setValue('zephyr')
    // Filter changes are debounced (300ms) before the fetch fires.
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith(expect.objectContaining({ search: 'zephyr', subjectId: 9 }))
  })

  it('refetches with the actor filter when the user types', async () => {
    listMock.mockResolvedValue({ data: [], total: 0 })

    const wrapper = await mountSuspended(AppActivityHistory, {
      props: { subjectType: 'user', subjectId: 9 }
    })
    await flushPromises()
    listMock.mockClear()

    await wrapper.find('input[placeholder="Who made the change"]').setValue('jane')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith(expect.objectContaining({ actor: 'jane', subjectId: 9 }))
  })
})
