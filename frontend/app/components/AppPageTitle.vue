<script setup lang="ts">
// The prominent page heading rendered at the top of the page body. Paired with
// <AppBreadcrumbTrail>, the small location line the layout renders directly
// beneath this title.
const { current } = useBreadcrumbs()
</script>

<template>
  <!-- Neutral high-emphasis, text-only title: safe contrast in both themes and
       calmer than repeating the sidebar's nav icon here. -->
  <h1
    v-if="current"
    class="text-title-large font-weight-bold my-0"
  >
    {{ current }}
  </h1>
</template>
