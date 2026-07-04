<script setup lang="ts">
// A page's intro row: an optional muted description on the left and page-level
// actions (via the default slot) pushed to the right. The heading itself comes
// from <AppBreadcrumbs> in the layout, so this only carries description + actions.
defineProps<{ description?: string }>()
</script>

<template>
  <div class="d-flex flex-wrap align-center ga-4 mb-6">
    <p
      v-if="description"
      class="text-body-medium text-medium-emphasis mb-0"
    >
      {{ description }}
    </p>
    <v-spacer />
    <slot />
  </div>
</template>
