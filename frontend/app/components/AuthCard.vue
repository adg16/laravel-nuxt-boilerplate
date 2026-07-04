<script setup lang="ts">
// Shared shell for the auth screens (login / forgot / reset): one place for the
// card padding and the title/subtitle header so the three pages don't repeat it.
defineProps<{
  title: string
  subtitle: string
}>()
</script>

<template>
  <v-card class="pa-6 pa-sm-8">
    <div class="mb-6">
      <div class="text-title-large font-weight-bold">
        {{ title }}
      </div>
      <p class="text-body-medium text-medium-emphasis mt-1">
        {{ subtitle }}
      </p>
    </div>

    <slot />
  </v-card>
</template>
