<script setup lang="ts">
import { useTheme, useDisplay } from 'vuetify'

// Left-nav items. Add app pages here — `to` is a Nuxt route, `icon` an MDI
// glyph. This is the single source for the sidebar menu.
type NavItem = { title: string, icon: string, to: string }

const navItems: NavItem[] = [
  { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: '/' },
  { title: 'Users', icon: 'mdi-account-group-outline', to: '/users' },
  { title: 'Roles', icon: 'mdi-shield-account-outline', to: '/roles' }
]

const auth = useAuthStore()
const router = useRouter()
const theme = useTheme()
const display = useDisplay()
const { appName, appTagline } = useRuntimeConfig().public

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

// Fullscreen toggle. Feature-detected so the button is hidden where the
// Fullscreen API is unavailable (e.g. iOS Safari), and kept in sync via the
// `fullscreenchange` event so the icon updates when the user exits with Esc.
const isFullscreen = ref(false)
const canFullscreen = ref(false)

function syncFullscreen() {
  isFullscreen.value = Boolean(document.fullscreenElement)
}

async function toggleFullscreen() {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen()
    } else {
      await document.documentElement.requestFullscreen()
    }
  } catch {
    // Non-critical: the browser may reject fullscreen (permissions/gesture
    // rules). Leave the current state untouched rather than surfacing an error.
  }
}

onMounted(() => {
  canFullscreen.value = Boolean(document.documentElement.requestFullscreen)
  document.addEventListener('fullscreenchange', syncFullscreen)
})

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', syncFullscreen)
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div>
    <!-- App bar is declared before the drawer so it claims the full width at the
         top; the drawer then sits below it. This keeps the brand (logo + name +
         tagline) always visible, independent of the drawer's collapse. -->
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
        <NuxtLink
          to="/"
          class="d-flex align-center ga-3 text-decoration-none text-high-emphasis"
        >
          <v-avatar
            rounded="lg"
            size="36"
          >
            <v-img
              src="/favicon.svg"
              alt=""
            />
          </v-avatar>
          <div class="d-flex flex-column">
            <span class="brand-name text-title-medium font-weight-bold">{{ appName }}</span>
            <span class="brand-tagline text-body-small text-medium-emphasis d-none d-sm-block">
              {{ appTagline }}
            </span>
          </div>
        </NuxtLink>
      </template>

      <template #append>
        <v-btn
          v-if="canFullscreen"
          :icon="isFullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
          :aria-label="isFullscreen ? 'Exit full screen' : 'Enter full screen'"
          variant="text"
          @click="toggleFullscreen"
        />

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

    <v-navigation-drawer
      v-model="drawer"
      :permanent="!isMobile"
      :rail="!isMobile"
      :expand-on-hover="!isMobile"
      elevation="2"
    >
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

    <v-main>
      <!-- The layout owns the content shell: consistent page padding plus the
           breadcrumb/title header (driven by each page's `breadcrumb` meta), so
           pages only render their body. -->
      <v-container
        fluid
        class="pa-4 pa-md-6"
      >
        <AppBreadcrumbs class="mb-6" />
        <slot />
      </v-container>
    </v-main>
  </div>
</template>

<style scoped>
/* Tighten the two brand lines so the name + tagline fit the app bar's height. */
.brand-name,
.brand-tagline {
  line-height: 1.25;
}
</style>
