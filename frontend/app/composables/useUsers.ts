import type { User } from '~/types/user'

// Thin wrapper over the user-management endpoints, mirroring how the auth store
// calls the API. Payloads: `roles` is a list of role names.
export interface UserPayload {
  name: string
  email: string
  roles: string[]
}

// Create additionally carries the access method: `invite` (default) or
// `set_password` with a password (only honored when the app allows the choice).
export interface CreateUserPayload extends UserPayload {
  method?: 'invite' | 'set_password'
  password?: string
  password_confirmation?: string
}

export function useUsers() {
  const api = useApi()

  return {
    list: () => api<User[]>('/users'),
    create: (body: CreateUserPayload) => api<User>('/users', { method: 'POST', body }),
    update: (id: number, body: UserPayload) => api<User>(`/users/${id}`, { method: 'PUT', body }),
    remove: (id: number) => api<{ message: string }>(`/users/${id}`, { method: 'DELETE' }),
    resendInvite: (id: number) => api<{ message: string }>(`/users/${id}/resend-invite`, { method: 'POST' }),
    resetTwoFactor: (id: number) => api<{ message: string }>(`/users/${id}/two-factor`, { method: 'DELETE' }),
    activate: (id: number) => api<{ message: string }>(`/users/${id}/activate`, { method: 'POST' }),
    deactivate: (id: number) => api<{ message: string }>(`/users/${id}/deactivate`, { method: 'POST' })
  }
}
