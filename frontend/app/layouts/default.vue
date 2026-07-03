<script setup lang="ts">
const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <UHeader>
      <template #left>
        <NuxtLink
          to="/"
          class="font-semibold"
        >
          App
        </NuxtLink>
      </template>

      <template #right>
        <UColorModeButton />
        <UButton
          v-if="auth.user"
          color="neutral"
          variant="ghost"
          icon="i-lucide-log-out"
          @click="handleLogout"
        >
          Logout
        </UButton>
      </template>
    </UHeader>

    <UMain class="flex-1">
      <slot />
    </UMain>
  </div>
</template>
