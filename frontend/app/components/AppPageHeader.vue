<script setup lang="ts">
// A page's intro elements. Page-level actions (the default slot, e.g. a "New …"
// button) teleport up into the layout's title row (#page-actions) so they align
// right of the heading. An optional muted description renders here in the page
// body, above the content. The heading itself comes from <AppPageTitle>.
defineProps<{ description?: string }>()
</script>

<template>
  <!-- Only teleport when a page actually provides actions: it skips the empty
       case and avoids a "missing #page-actions target" warning wherever this is
       mounted without the default layout (e.g. isolated component tests).
       `defer` waits for the layout's target to exist before the teleport mounts. -->
  <Teleport
    v-if="$slots.default"
    to="#page-actions"
    defer
  >
    <slot />
  </Teleport>

  <p
    v-if="description"
    class="text-body-medium text-medium-emphasis mb-6"
  >
    {{ description }}
  </p>
</template>
