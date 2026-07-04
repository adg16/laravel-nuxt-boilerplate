<script setup lang="ts">
import { useTheme, useDisplay } from 'vuetify'

// Left-nav items. Add app pages here — `to` is a Nuxt route, `icon` an MDI
// glyph. This is the single source for the sidebar menu.
type NavItem = { title: string, icon: string, to: string }

const navItems: NavItem[] = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { title: 'Users', icon: 'mdi-account-group-outline', to: '/users' }
]

const auth = useAuthStore()
const router = useRouter()
const theme = useTheme()
const display = useDisplay()
const { appName } = useRuntimeConfig().public

const isMobile = computed(() => display.mobile.value)

// Mobile: `drawer` opens/closes the temporary overlay (Vuetify makes the drawer
// temporary automatically below the mobile breakpoint). Desktop keeps a
// permanent icon rail that expands on hover — no toggle needed.
const drawer = ref(!display.mobile.value)

const isDark = computed(() => theme.global.current.value.dark)

const userInitials = computed(() => getInitials(auth.user?.name))

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
    <v-navigation-drawer
      v-model="drawer"
      :permanent="!isMobile"
      :rail="!isMobile"
      :expand-on-hover="!isMobile"
      elevation="3"
    >
      <v-list
        nav
        class="py-0"
      >
        <v-list-item class="brand-header">
          <template #prepend>
            <v-avatar
              rounded="lg"
              class="brand-logo"
            >
              <v-img
                src="/favicon.svg"
                alt=""
              />
            </v-avatar>
          </template>
          <template #title>
            <span class="text-title-medium font-weight-bold">{{ appName }}</span>
          </template>
        </v-list-item>
      </v-list>

      <v-divider />

      <v-list
        nav
        density="comfortable"
      >
        <v-list-item
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :prepend-icon="item.icon"
          :title="item.title"
          color="primary"
          rounded="lg"
        />
      </v-list>
    </v-navigation-drawer>

    <v-app-bar
      flat
      border="b"
    >
      <template #prepend>
        <v-app-bar-nav-icon
          v-if="isMobile"
          aria-label="Toggle menu"
          @click="drawer = !drawer"
        />
      </template>

      <v-app-bar-title class="text-title-medium font-weight-medium" />

      <template #append>
        <v-btn
          :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
          :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
          variant="text"
          @click="toggleTheme"
        />

        <v-menu
          v-if="auth.user"
          location="bottom end"
        >
          <template #activator="{ props }">
            <v-btn
              v-bind="props"
              icon
              aria-label="Account menu"
            >
              <v-avatar
                color="primary"
                size="34"
              >
                <span class="text-label-medium font-weight-bold">{{ userInitials }}</span>
              </v-avatar>
            </v-btn>
          </template>

          <v-list
            width="220"
            density="comfortable"
          >
            <v-list-item
              :title="auth.user.name"
              :subtitle="auth.user.email"
            >
              <template #prepend>
                <v-avatar
                  color="primary"
                  size="36"
                >
                  <span class="text-label-medium font-weight-bold">{{ userInitials }}</span>
                </v-avatar>
              </template>
            </v-list-item>

            <v-divider />

            <v-list-item
              prepend-icon="mdi-logout"
              title="Logout"
              @click="handleLogout"
            />
          </v-list>
        </v-menu>
      </template>
    </v-app-bar>

    <v-main>
      <slot />
    </v-main>
  </div>
</template>

<style scoped>
/* Match the app bar's 64px height so the drawer's header divider lines up with
   the app bar's bottom border. */
.brand-header {
  min-height: 64px;
}

/* Keep the logo a constant 36px in both the collapsed rail and the expanded
   drawer. We size it via the avatar's CSS variable (not the `size` prop, whose
   inline width/height would be overridden by Vuetify's rail rule); scoped styles
   are unlayered and therefore win over that layered rail rule.

   The 36px logo and the 24px nav icons share the same left edge, so the larger
   logo's centre sits (36 − 24) / 2 = 6px further right. Nudge it back 6px in
   both states so its centre stays on the nav-icon column and its left margin is
   identical whether the drawer is collapsed or expanded. */
.brand-logo {
  --v-avatar-height: 36px;
  transform: translateX(-6px);
}
</style>
