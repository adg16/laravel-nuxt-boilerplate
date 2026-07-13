// Authorization helpers for the UI, reading the current user's roles and
// permissions from the auth store. Use these to guard buttons/sections
// (`<Can>` wraps this) and to filter navigation.
//
// `can()` mirrors the backend's Gate::before: the `super-admin` role passes
// every check regardless of its explicit permissions. Keep that in sync with
// AppServiceProvider if the bypass rule ever changes.
const SUPER_ADMIN_ROLE = 'Super Admin'

export function useAuthz() {
  const auth = useAuthStore()

  function hasRole(role: string): boolean {
    return auth.user?.roles.includes(role) ?? false
  }

  function can(permission: string): boolean {
    return hasRole(SUPER_ADMIN_ROLE) || (auth.user?.permissions.includes(permission) ?? false)
  }

  function canAny(permissions: string[]): boolean {
    return permissions.some(can)
  }

  return { can, canAny, hasRole }
}
