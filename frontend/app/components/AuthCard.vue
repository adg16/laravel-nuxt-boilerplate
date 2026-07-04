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
      <h1 class="text-h5 font-weight-bold">
        {{ title }}
      </h1>
      <p class="text-body-2 text-medium-emphasis mt-1">
        {{ subtitle }}
      </p>
    </div>

    <slot />
  </v-card>
</template>
