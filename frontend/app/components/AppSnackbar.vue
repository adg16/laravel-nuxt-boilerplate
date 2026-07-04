<script setup lang="ts">
// Single app-wide snackbar host; messages are pushed via useSnackbar().notify.
const { show, text, color } = useSnackbar()
</script>

<template>
  <v-snackbar
    v-model="show"
    :color="color"
    location="top"
    timeout="5000"
  >
    {{ text }}

    <template #actions>
      <v-btn
        variant="text"
        icon="mdi-close"
        aria-label="Dismiss"
        @click="show = false"
      />
    </template>
  </v-snackbar>
</template>
