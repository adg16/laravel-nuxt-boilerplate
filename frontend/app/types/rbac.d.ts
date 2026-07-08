// Shapes returned by the role/permission management endpoints (RoleResource,
// PermissionResource). Responses are unwrapped (no `data` key) — see the
// backend's JsonResource::withoutWrapping().

export interface Role {
  id: number
  name: string
  permissions: string[]
  users_count?: number
}

export interface Permission {
  id: number
  name: string
  // Names of the roles that hold this permission (for the read-only catalog).
  roles: string[]
}
