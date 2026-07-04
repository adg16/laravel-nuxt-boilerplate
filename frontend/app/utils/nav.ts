export type NavItem = { title: string, icon: string, to: string }

// Single source for the sidebar menu — also used to pick the current page's icon
// for the app-bar breadcrumb. Add app pages here: `to` is a Nuxt route, `icon`
// an MDI glyph.
export const navItems: NavItem[] = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { title: 'Users', icon: 'mdi-account-group-outline', to: '/users' },
  { title: 'Roles', icon: 'mdi-shield-account-outline', to: '/roles' }
]
