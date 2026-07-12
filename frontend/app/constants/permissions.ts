// AUTO-GENERATED from App\Enums\Permission — do not edit by hand.
// Run `make sync-permissions` after changing the Permission enum.
export const PERMISSIONS = {
  UsersView: 'users.view',
  UsersManage: 'users.manage',
  RolesView: 'roles.view',
  RolesManage: 'roles.manage',
  SettingsView: 'settings.view',
  SettingsManage: 'settings.manage'
} as const

export type PermissionName = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]
