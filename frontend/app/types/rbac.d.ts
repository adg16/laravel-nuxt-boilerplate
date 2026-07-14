// Shapes returned by the role/permission management endpoints (RoleResource,
// PermissionResource). Responses are unwrapped (no `data` key) — see the
// backend's JsonResource::withoutWrapping().

// Who created / last updated a record, or null when there's no actor (seeded /
// automated writes) or the actor is redacted (a super-admin / System account
// hidden from a non-super-admin viewer). See the backend's HasBlameStamps.
export interface BlameStamp {
  id: number
  name: string
}

export interface Role {
  id: number
  name: string
  permissions: string[]
  users_count?: number
  created_at: string
  updated_at: string
  created_by: BlameStamp | null
  updated_by: BlameStamp | null
}

export interface Permission {
  id: number
  name: string
  // Names of the roles that hold this permission (for the read-only catalog).
  roles: string[]
}
