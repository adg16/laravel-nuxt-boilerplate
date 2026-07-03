<script setup lang="ts">
const auth = useAuthStore()
</script>

<template>
  <UContainer class="py-12">
    <UPageCard
      title="Welcome"
      :description="`Signed in as ${auth.user?.email ?? ''}`"
    />
  </UContainer>
</template>
