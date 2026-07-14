export type Crumb = { title: string, to?: string }

// Shared source for the page location/title, derived from each matched route's
// `breadcrumb` meta. Consumed by <AppBreadcrumbTrail> (the small location line)
// and <AppPageTitle> (the page heading) so the two stay in sync from one place.
//
// Meta titles are i18n message keys (they must be static for the
// `definePageMeta` macro), translated here so the labels localize.
export function useBreadcrumbs() {
  const route = useRoute()
  const { t } = useI18n()

  // A string meta becomes a single crumb linked to that route; an array is
  // spread in as-is. The last crumb is the current page.
  const crumbs = computed<Crumb[]>(() => {
    const list: Crumb[] = []
    for (const record of route.matched) {
      const meta = record.meta.breadcrumb
      if (!meta) continue
      if (typeof meta === 'string') list.push({ title: t(meta), to: record.path })
      else list.push(...meta.map(crumb => ({ title: t(crumb.title), to: crumb.to })))
    }
    return list
  })

  const current = computed(() => crumbs.value.at(-1)?.title ?? '')

  // The crumb one level up — the "back" target for nested pages (undefined on
  // top-level pages, which have a single crumb). Parent crumbs carry a `to`.
  const parent = computed(() => crumbs.value.at(-2))

  return { crumbs, current, parent }
}
