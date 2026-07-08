import type { Role } from '~/types/rbac'

export interface RolePayload {
  name: string
  permissions: string[]
}

export function useRoles() {
  const api = useApi()

  return {
    list: () => api<Role[]>('/roles'),
    create: (body: RolePayload) => api<Role>('/roles', { method: 'POST', body }),
    update: (id: number, body: RolePayload) => api<Role>(`/roles/${id}`, { method: 'PUT', body }),
    remove: (id: number) => api<{ message: string }>(`/roles/${id}`, { method: 'DELETE' })
  }
}
