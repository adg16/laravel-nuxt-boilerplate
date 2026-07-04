<script setup lang="ts">
const auth = useAuthStore()
</script>

<template>
  <v-container class="py-12">
    <v-card
      title="Welcome"
      :subtitle="`Signed in as ${auth.user?.email ?? ''}`"
    />
  </v-container>
</template>
