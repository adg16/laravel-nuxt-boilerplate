<script setup lang="ts">
// The prominent page heading (+ optional subtitle) rendered at the top of the
// page body. Paired with <AppBreadcrumbTrail>, which carries the small location
// line in the app bar (desktop) or above this title (mobile).
const { current, subtitle } = useBreadcrumbs()
</script>

<template>
  <div
    v-if="current"
    class="mb-6"
  >
    <!-- Neutral high-emphasis, text-only title: safe contrast in both themes and
         calmer than repeating the sidebar's nav icon here. -->
    <h1 class="text-headline-small font-weight-bold my-0">
      {{ current }}
    </h1>
    <p
      v-if="subtitle"
      class="text-body-medium text-medium-emphasis mt-1 mb-0"
    >
      {{ subtitle }}
    </p>
  </div>
</template>
