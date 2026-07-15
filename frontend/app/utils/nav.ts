import { PERMISSIONS } from '~/constants/permissions'
import { SUPER_ADMIN_ROLE } from '~/constants/roles'

export type NavItem = {
  titleKey: string
  icon: string
  // Either an internal Nuxt route (`to`) or an external URL (`href`, opened in a
  // new tab — e.g. a server-rendered page that lives outside the SPA).
  to?: string
  href?: string
  // Optional permission required to see this item; unguarded items always show.
  // Filtered against the current user in the layout via `useAuthz`.
  permission?: string
  // Optional role required to see this item, checked with `useAuthz().hasRole`.
  role?: string
  // Optional section tag. Items tagged 'admin' (management/infra tools) are
  // rendered below a divider that separates them from the main navigation.
  section?: 'admin'
}

// Single source for the sidebar menu. `titleKey` is an i18n message key (see
// `i18n/locales/*.json`), resolved with `$t` where the menu is rendered so the
// labels localize. Add app pages here: `to` is a Nuxt route, `icon` an MDI glyph.
export const navItems: NavItem[] = [
  { titleKey: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { titleKey: 'nav.users', icon: 'mdi-account-group-outline', to: '/users', permission: PERMISSIONS.UsersView },
  { titleKey: 'nav.roles', icon: 'mdi-shield-account-outline', to: '/roles', permission: PERMISSIONS.RolesView },
  { titleKey: 'nav.activityLog', icon: 'mdi-history', to: '/activity-log', permission: PERMISSIONS.ActivityView, section: 'admin' },
  { titleKey: 'nav.settings', icon: 'mdi-cog-outline', to: '/settings', permission: PERMISSIONS.SettingsView, section: 'admin' },
  // Horizon queue dashboard: a server-rendered page outside the SPA, so an
  // external `href` (new tab); Super-Admin-only, mirroring the backend
  // `viewHorizon` gate.
  { titleKey: 'nav.horizon', icon: 'mdi-chart-timeline-variant', href: '/horizon', role: SUPER_ADMIN_ROLE, section: 'admin' }
]
