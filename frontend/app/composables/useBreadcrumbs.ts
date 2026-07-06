export type Crumb = { title: string, to?: string }

// Shared source for the page location/title, derived from each matched route's
// `breadcrumb` meta. Consumed by <AppBreadcrumbTrail> (the small location line)
// and <AppPageTitle> (the page heading) so the two stay in sync from one place.
export function useBreadcrumbs() {
  const route = useRoute()

  // A string meta becomes a single crumb linked to that route; an array is
  // spread in as-is. The last crumb is the current page.
  const crumbs = computed<Crumb[]>(() => {
    const list: Crumb[] = []
    for (const record of route.matched) {
      const meta = record.meta.breadcrumb
      if (!meta) continue
      if (typeof meta === 'string') list.push({ title: meta, to: record.path })
      else list.push(...meta)
    }
    return list
  })

  const current = computed(() => crumbs.value.at(-1)?.title ?? '')

  // Optional subtitle, shown under the title (set via `definePageMeta`).
  const subtitle = computed(() => route.meta.subtitle)

  return { crumbs, current, subtitle }
}
