<script setup lang="ts">
type Crumb = { title: string, to?: string }

const route = useRoute()

// Collect crumbs from each matched route's `breadcrumb` meta — a string becomes
// a single crumb linked to that route, an array is spread in as-is. The last
// crumb is the current page: rendered as the heading below, not as a link.
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

// Parent trail (everything above the current page) shown as a small linked row.
const trail = computed(() => crumbs.value.slice(0, -1))
const current = computed(() => crumbs.value.at(-1)?.title ?? '')

// Icon for the current page, taken from the shared nav config (matched by path)
// so it stays in sync with the sidebar without duplicating it per page.
const currentIcon = computed(() => navItems.find(item => item.to === route.path)?.icon)

// Optional subtitle, shown under the title (set via `definePageMeta`).
const subtitle = computed(() => route.meta.subtitle)
</script>

<template>
  <div v-if="current">
    <v-breadcrumbs
      v-if="trail.length"
      :items="trail"
      class="pa-0 mb-1 text-body-small"
    >
      <template #divider>
        <v-icon
          icon="mdi-chevron-right"
          size="16"
        />
      </template>
    </v-breadcrumbs>
    <h1 class="d-flex align-center ga-2 text-title-large font-weight-bold text-primary my-0">
      <v-icon
        v-if="currentIcon"
        :icon="currentIcon"
        size="small"
      />
      {{ current }}
    </h1>
    <p
      v-if="subtitle"
      class="text-body-small text-medium-emphasis my-0"
    >
      {{ subtitle }}
    </p>
  </div>
</template>
