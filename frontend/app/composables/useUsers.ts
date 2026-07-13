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

// Server-side pagination/sort/filter params for the users list.
export interface UserListParams {
  page: number
  perPage: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  name?: string
  email?: string
  roles?: string[]
  accountStatus?: string[]
  verificationStatus?: string[]
}

export interface UserListResult {
  data: User[]
  total: number
}

export function useUsers() {
  const api = useApi()

  return {
    list: (params: UserListParams) => api<UserListResult>('/users', {
      query: {
        page: params.page,
        per_page: params.perPage,
        sort_by: params.sortBy || undefined,
        sort_dir: params.sortDir || undefined,
        name: params.name?.trim() || undefined,
        email: params.email?.trim() || undefined,
        // Comma-joined so they survive query serialization as a single value the
        // backend splits (repeated `roles=` keys don't round-trip to a PHP array).
        roles: params.roles?.length ? params.roles.join(',') : undefined,
        account_status: params.accountStatus?.length ? params.accountStatus.join(',') : undefined,
        verification_status: params.verificationStatus?.length ? params.verificationStatus.join(',') : undefined
      }
    }),
    create: (body: CreateUserPayload) => api<User>('/users', { method: 'POST', body }),
    update: (id: number, body: UserPayload) => api<User>(`/users/${id}`, { method: 'PUT', body }),
    remove: (id: number) => api<{ message: string }>(`/users/${id}`, { method: 'DELETE' }),
    resendInvite: (id: number) => api<{ message: string }>(`/users/${id}/resend-invite`, { method: 'POST' }),
    resetTwoFactor: (id: number) => api<{ message: string }>(`/users/${id}/two-factor`, { method: 'DELETE' }),
    activate: (id: number) => api<{ message: string }>(`/users/${id}/activate`, { method: 'POST' }),
    deactivate: (id: number) => api<{ message: string }>(`/users/${id}/deactivate`, { method: 'POST' })
  }
}
