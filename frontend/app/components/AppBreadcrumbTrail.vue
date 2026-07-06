<script setup lang="ts">
// The small "where am I" location line for the app bar. Renders the full trail
// including the current page as the last, non-linked item (a conventional
// breadcrumb). The prominent page heading lives separately in <AppPageTitle>.
const { crumbs } = useBreadcrumbs()

// Strip `to` from the current (last) crumb so it renders as plain text, not a
// link back to the page you're already on.
const items = computed(() => crumbs.value.map(
  (crumb, i) => i === crumbs.value.length - 1 ? { title: crumb.title } : crumb
))
</script>

<template>
  <v-breadcrumbs
    v-if="items.length"
    :items="items"
    class="pa-0 text-body-small text-medium-emphasis"
  >
    <template #divider>
      <v-icon
        icon="mdi-chevron-right"
        size="16"
      />
    </template>
  </v-breadcrumbs>
</template>

<style scoped>
/* Color only the linked (parent) crumbs primary so they clearly read as
   navigable; the current-page crumb has no `to`, so it stays the muted color
   inherited from the wrapper. Vuetify keeps the hover underline as the extra
   affordance. */
.v-breadcrumbs :deep(.v-breadcrumbs-item--link) {
  color: rgb(var(--v-theme-primary));
}
</style>
