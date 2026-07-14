import type { BlameStamp } from './rbac'

// The second factor a user has enrolled.
export type TwoFactorMethod = 'totp' | 'email'

export interface User {
  id: number
  name: string
  email: string
  // Same-origin URL to the user's avatar image (streamed by the API), or null
  // when they haven't uploaded one. Carries a cache-busting `?v=` that changes
  // with the image.
  avatar_url: string | null
  // Role names and the flattened set of permission names the user holds
  // (via their roles). Drives the frontend guards in `useAuthz`.
  roles: string[]
  permissions: string[]
  // Protected accounts (super-admin / System) can't be edited or deleted; the
  // Users table disables their row actions off this flag.
  is_protected: boolean
  // Whether the user has accepted their invitation and set a password.
  is_verified: boolean
  // Whether the account is active. Deactivated users can't sign in and are cut
  // off from the API; the Users table exposes an activate/deactivate toggle.
  is_active: boolean
  // Whether the user has an active (confirmed) two-factor setup. Drives the
  // Security page state and the required-mode enrollment gate.
  two_factor_enabled: boolean
  // Which second factor is enrolled, or null.
  two_factor_method: 'totp' | 'email' | null
  created_at: string
  updated_at: string
  // Who created / last updated the account, or null when there's no actor or the
  // actor is redacted (a restricted account hidden from the viewer).
  created_by: BlameStamp | null
  updated_by: BlameStamp | null
}
