<script setup lang="ts">
// A user's avatar: their uploaded image when set, else their initials on the
// brand color. Single-sourced so the app bar, account menu, user list, and
// profile page stay consistent. Initials scale with `size`.
const props = withDefaults(defineProps<{
  name?: string | null
  src?: string | null
  size?: number
}>(), { size: 40 })

const initials = computed(() => getInitials(props.name))
const fontSize = computed(() => `${Math.round(props.size * 0.4)}px`)
</script>

<template>
  <v-avatar
    :size="size"
    :color="src ? undefined : 'primary'"
  >
    <v-img
      v-if="src"
      :src="src"
      :alt="name ?? ''"
      cover
    />
    <span
      v-else
      class="font-weight-bold"
      :style="{ fontSize }"
    >{{ initials }}</span>
  </v-avatar>
</template>
