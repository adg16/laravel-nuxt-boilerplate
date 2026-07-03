<template>
  <div class="relative min-h-dvh flex flex-col items-center justify-center overflow-hidden bg-muted/30 px-4 py-10 sm:px-6">
    <!-- Ambient background accents -->
    <div
      aria-hidden="true"
      class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-primary/20 blur-3xl"
    />
    <div
      aria-hidden="true"
      class="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-primary/10 blur-3xl"
    />

    <div class="relative w-full max-w-sm">
      <div class="mb-8 flex flex-col items-center text-center">
        <div class="flex size-12 items-center justify-center rounded-2xl bg-primary text-inverted shadow-lg shadow-primary/30">
          <UIcon
            name="i-lucide-hexagon"
            class="size-6"
          />
        </div>
        <span class="mt-3 text-lg font-semibold tracking-tight text-highlighted">
          {{ appName }}
        </span>
      </div>

      <slot />

      <p class="mt-8 text-center text-xs text-muted">
        &copy; {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
const appName = 'Boilerplate'
</script>
