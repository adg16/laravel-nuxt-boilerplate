<script setup lang="ts">
import { useTheme, useDisplay } from 'vuetify'

// `navItems` (the sidebar menu) is the single source in `app/utils/nav.ts`,
// shared with <AppBreadcrumbs> — auto-imported here.
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
    <v-navigation-drawer
      v-model="drawer"
      :permanent="!isMobile"
      :rail="!isMobile"
      :expand-on-hover="!isMobile"
      :width="360"
      elevation="2"
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
            <div class="d-flex flex-column">
              <span class="text-title-medium font-weight-bold">{{ appName }}</span>
              <!-- Tagline stacks under the name; hidden in the collapsed rail,
                   revealed when the drawer expands (see the rail rule in <style>). -->
              <span class="brand-tagline text-body-small text-medium-emphasis">
                {{ appTagline }}
              </span>
            </div>
          </template>
        </v-list-item>
      </v-list>

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

    <v-app-bar flat>
      <template #prepend>
        <v-app-bar-nav-icon
          v-if="isMobile"
          aria-label="Toggle menu"
          @click="drawer = !drawer"
        />
        <!-- Desktop: page title/breadcrumb left-aligned in the bar (in `prepend`
             so it hugs the left edge rather than centering in the empty title
             area). On mobile it moves below the app bar — see <v-main>. -->
        <AppBreadcrumbs
          v-if="!isMobile"
          class="ms-5"
        />
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

    <v-main>
      <!-- The layout owns the content shell: consistent page padding (the
           breadcrumb/page title lives in the app bar), so pages only render
           their body. -->
      <v-container
        fluid
        class="pa-4 pa-md-6"
      >
        <!-- Mobile: breadcrumb sits below the app bar (below the burger menu);
             on desktop it lives in the app bar instead. -->
        <AppBreadcrumbs
          v-if="isMobile"
          class="mb-4"
        />
        <slot />
      </v-container>
    </v-main>
  </div>
</template>

<style scoped>
/* Match the app bar's 64px height so the drawer's header divider lines up with
   the app bar's bottom border. `--v-list-prepend-gap` tightens the space between
   the logo and the app name (Vuetify defaults it to 16px for avatars). */
.brand-header {
  min-height: 64px;
  --v-list-prepend-gap: 8px;
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

/* Keep the tagline on a single line so it never reflows while the drawer's width
   animates open (multi-line reflow would change the content height and re-centre
   the logo/name — the flicker). The drawer is widened enough to fit it. */
.brand-tagline {
  white-space: nowrap;
  line-height: 1.25;
  transition: opacity 0.2s ease;
}

/* Hide the tagline in the collapsed rail with opacity (not display) so its line
   stays reserved — the content height is constant, so nothing shifts vertically
   when the drawer expands; the tagline just fades in as it opens. */
.v-navigation-drawer--rail:not(.v-navigation-drawer--is-hovering) .brand-tagline {
  opacity: 0;
}
</style>
