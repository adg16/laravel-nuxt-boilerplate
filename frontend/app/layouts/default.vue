<script setup lang="ts">
import { useTheme } from 'vuetify'

const auth = useAuthStore()
const router = useRouter()
const theme = useTheme()
const { appName } = useRuntimeConfig().public

const isDark = computed(() => theme.global.current.value.dark)

function toggleTheme() {
  theme.global.name.value = isDark.value ? 'light' : 'dark'
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div>
    <v-app-bar
      flat
      border="b"
    >
      <v-app-bar-title>
        <NuxtLink
          to="/"
          class="text-decoration-none text-high-emphasis font-weight-bold"
        >
          {{ appName }}
        </NuxtLink>
      </v-app-bar-title>

      <template #append>
        <v-btn
          :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
          :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
          variant="text"
          @click="toggleTheme"
        />
        <v-btn
          v-if="auth.user"
          prepend-icon="mdi-logout"
          variant="text"
          @click="handleLogout"
        >
          Logout
        </v-btn>
      </template>
    </v-app-bar>

    <v-main>
      <slot />
    </v-main>
  </div>
</template>
