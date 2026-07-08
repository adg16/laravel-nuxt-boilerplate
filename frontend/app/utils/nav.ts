import { PERMISSIONS } from '~/constants/permissions'

export type NavItem = {
  titleKey: string
  icon: string
  to: string
  // Optional permission required to see this item; unguarded items always show.
  // Filtered against the current user in the layout via `useAuthz`.
  permission?: string
}

// Single source for the sidebar menu. `titleKey` is an i18n message key (see
// `i18n/locales/*.json`), resolved with `$t` where the menu is rendered so the
// labels localize. Add app pages here: `to` is a Nuxt route, `icon` an MDI glyph.
export const navItems: NavItem[] = [
  { titleKey: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { titleKey: 'nav.users', icon: 'mdi-account-group-outline', to: '/users', permission: PERMISSIONS.UsersView },
  { titleKey: 'nav.roles', icon: 'mdi-shield-account-outline', to: '/roles', permission: PERMISSIONS.RolesView },
  { titleKey: 'nav.permissions', icon: 'mdi-key-outline', to: '/permissions', permission: PERMISSIONS.PermissionsView },
  { titleKey: 'nav.settings', icon: 'mdi-cog-outline', to: '/settings', permission: PERMISSIONS.SettingsView }
]
