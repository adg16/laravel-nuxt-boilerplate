// Custom `definePageMeta` keys for this app. Augmenting Nuxt's PageMeta keeps
// the meta typed at both the definition site and when read from `route.meta`.
declare module '#app' {
  interface PageMeta {
    // Breadcrumb label(s) for the route, given as i18n message keys (they must
    // be static for the definePageMeta macro; useBreadcrumbs translates them):
    // a string for a single crumb, or an ordered array of { title, to } for
    // multi-level pages. The last crumb is the current page — rendered as the
    // heading by <AppPageTitle> and as the final, non-linked item of
    // <AppBreadcrumbTrail>.
    breadcrumb?: string | { title: string, to?: string }[]
    // Optional permission required to view the route. Enforced by the global
    // auth middleware (redirects home if the user lacks it) — a permission
    // name from `~/constants/permissions`.
    permission?: string
  }
}

export {}
