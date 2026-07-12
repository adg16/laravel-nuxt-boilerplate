import type { Role } from '~/types/rbac'

export interface RolePayload {
  name: string
  permissions: string[]
}

// Server-side pagination/sort/filter params for the roles list.
export interface RoleListParams {
  page: number
  perPage: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  name?: string
  permissions?: string[]
}

export interface RoleListResult {
  data: Role[]
  total: number
}

export function useRoles() {
  const api = useApi()

  return {
    list: (params: RoleListParams) => api<RoleListResult>('/roles', {
      query: {
        page: params.page,
        per_page: params.perPage,
        sort_by: params.sortBy || undefined,
        sort_dir: params.sortDir || undefined,
        name: params.name?.trim() || undefined,
        permissions: params.permissions?.length ? params.permissions.join(',') : undefined
      }
    }),
    // Role names for filter/select dropdowns — fetches a single generous page
    // (there are rarely more than a handful of roles).
    options: async () => (await api<RoleListResult>('/roles', { query: { per_page: 100 } })).data.map(role => role.name),
    create: (body: RolePayload) => api<Role>('/roles', { method: 'POST', body }),
    update: (id: number, body: RolePayload) => api<Role>(`/roles/${id}`, { method: 'PUT', body }),
    remove: (id: number) => api<{ message: string }>(`/roles/${id}`, { method: 'DELETE' })
  }
}
