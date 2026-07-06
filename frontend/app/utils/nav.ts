export type NavItem = { titleKey: string, icon: string, to: string }

// Single source for the sidebar menu. `titleKey` is an i18n message key (see
// `i18n/locales/*.json`), resolved with `$t` where the menu is rendered so the
// labels localize. Add app pages here: `to` is a Nuxt route, `icon` an MDI glyph.
export const navItems: NavItem[] = [
  { titleKey: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { titleKey: 'nav.users', icon: 'mdi-account-group-outline', to: '/users' },
  { titleKey: 'nav.roles', icon: 'mdi-shield-account-outline', to: '/roles' }
]
