// The second factor a user has enrolled.
export type TwoFactorMethod = 'totp' | 'email'

export interface User {
  id: number
  name: string
  email: string
  // Role names and the flattened set of permission names the user holds
  // (via their roles). Drives the frontend guards in `useAuthz`.
  roles: string[]
  permissions: string[]
  // Protected accounts (super-admin / System) can't be edited or deleted; the
  // Users table disables their row actions off this flag.
  is_protected: boolean
  // Whether the user has accepted their invitation and set a password.
  is_verified: boolean
  // Whether the user has an active (confirmed) two-factor setup. Drives the
  // Security page state and the required-mode enrollment gate.
  two_factor_enabled: boolean
  // Which second factor is enrolled, or null.
  two_factor_method: 'totp' | 'email' | null
  created_at: string
}
