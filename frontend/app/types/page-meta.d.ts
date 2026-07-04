// Custom `definePageMeta` keys for this app. Augmenting Nuxt's PageMeta keeps
// the meta typed at both the definition site and when read from `route.meta`.
declare module '#app' {
  interface PageMeta {
    // Breadcrumb label(s) for the route: a string for a single crumb, or an
    // ordered array of { title, to } for multi-level pages. The last crumb is
    // rendered as the page heading (not a link) by <AppBreadcrumbs>.
    breadcrumb?: string | { title: string, to?: string }[]
  }
}

export {}
