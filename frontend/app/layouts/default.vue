<script setup lang="ts">
import { useTheme, useDisplay } from 'vuetify'

// `navItems` (the sidebar menu) is the single source in `app/utils/nav.ts` —
// auto-imported here.
const auth = useAuthStore()
const { can, hasRole } = useAuthz()
const router = useRouter()

// Hide nav items the user isn't permitted to see; unguarded items always show.
// Items may be gated by a permission and/or a role (e.g. the Super-Admin-only
// Horizon link) — both must pass when present.
const visibleNavItems = computed(() =>
  navItems.filter(item =>
    (!item.permission || can(item.permission)) && (!item.role || hasRole(item.role))
  )
)
// Position of the first visible 'admin'-section item, so the render can draw a
// divider just above it (separating admin tools from the main nav). -1 when no
// admin item is visible, so the divider is omitted entirely.
const adminDividerIndex = computed(() =>
  visibleNavItems.value.findIndex(item => item.section === 'admin')
)
// The "back" target for nested pages (the breadcrumb parent) — undefined on
// top-level pages, so the back button only shows where there's a level to go up.
const { parent } = useBreadcrumbs()
const theme = useTheme()
const display = useDisplay()
const { appName, appTagline } = useRuntimeConfig().public

const isMobile = computed(() => display.mobile.value)

// Desktop can "pin" the drawer into the classic always-visible icon rail that
// expands on hover (the previous behavior). Persisted in a cookie so the choice
// survives reloads. Mobile is always a temporary overlay, so `isRail` ignores
// the pin there.
const railPinned = useCookie<boolean>('nav_pinned', { default: () => false })
const isRail = computed(() => !isMobile.value && railPinned.value)

// Drawer visibility. As a temporary overlay it is hidden by default and toggled
// by the app-bar burger; as a pinned rail it must stay open. Vuetify only
// auto-opens a permanent drawer when its `v-model` is *unbound* — since we bind
// it, we must keep the model `true` ourselves while pinned. `watchEffect` runs
// immediately, so this also restores the rail after a refresh reads the cookie
// (otherwise `permanent` flips to true with no change event to open it).
const drawer = ref(isRail.value)
watchEffect(() => {
  if (isRail.value) drawer.value = true
})

function togglePin() {
  railPinned.value = !railPinned.value
  // Unpinning turns the drawer back into a temporary overlay — collapse it so it
  // doesn't linger open over the content (`watchEffect` handles re-opening when
  // pinning).
  if (!railPinned.value) drawer.value = false
}

const isDark = computed(() => theme.global.current.value.dark)

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
  // logout() always clears client state (even if the request 403s for a
  // deactivated session), so redirect regardless of the request outcome.
  try {
    await auth.logout()
  } finally {
    router.push('/login')
  }
}
</script>

<template>
  <div>
    <!-- Top bar spans the full screen width: it's registered before the drawer,
         so Vuetify's layout gives it the entire top edge and insets the drawer
         below it (the drawer no longer occupies the top-left corner). -->
    <v-app-bar
      elevation="1"
    >
      <template #prepend>
        <!-- The burger toggles the nav drawer and sits to the left of the brand.
             Hidden when the drawer is pinned as a permanent rail (nothing to
             toggle then). -->
        <v-app-bar-nav-icon
          v-if="!isRail"
          :aria-label="$t('a11y.toggleMenu')"
          @click="drawer = !drawer"
        />
        <!-- Brand logo hugs the left edge of the top bar (right of the burger).
             On desktop the app name and tagline follow it; on mobile only the
             logo shows. The prominent page title (with the breadcrumb trail
             beneath it) lives in the body via <AppPageTitle> — see <v-main>. -->
        <v-avatar
          rounded="lg"
          size="32"
          class="ms-1"
        >
          <v-img
            src="/favicon.svg"
            alt=""
          />
        </v-avatar>
        <div
          v-if="!isMobile"
          class="ms-2 d-flex flex-column"
        >
          <span class="text-title-medium font-weight-bold">{{ appName }}</span>
          <span class="text-body-small text-medium-emphasis">{{ appTagline }}</span>
        </div>
      </template>

      <template #append>
        <AppLanguageSwitcher />

        <v-btn
          v-if="canFullscreen"
          :icon="isFullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
          :aria-label="isFullscreen ? $t('a11y.exitFullscreen') : $t('a11y.enterFullscreen')"
          variant="text"
          @click="toggleFullscreen"
        />

        <v-btn
          :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
          :aria-label="isDark ? $t('a11y.switchToLight') : $t('a11y.switchToDark')"
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
              :aria-label="$t('a11y.accountMenu')"
            >
              <AppUserAvatar
                :name="auth.user?.name"
                :src="auth.user?.avatar_url"
                :size="34"
              />
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
                <AppUserAvatar
                  :name="auth.user.name"
                  :src="auth.user.avatar_url"
                  :size="36"
                />
              </template>
            </v-list-item>

            <v-divider />

            <v-list-item
              to="/profile"
              prepend-icon="mdi-account-circle-outline"
              :title="$t('nav.profile')"
            />

            <v-list-item
              to="/security"
              prepend-icon="mdi-shield-lock-outline"
              :title="$t('nav.security')"
            />

            <v-list-item
              prepend-icon="mdi-logout"
              :title="$t('common.logout')"
              @click="handleLogout"
            />
          </v-list>
        </v-menu>
      </template>
    </v-app-bar>

    <!-- Two modes: a temporary hidden-by-default overlay (the burger toggles it,
         the scrim/Esc dismisses it) by default, or — when pinned on desktop — a
         permanent icon rail that expands on hover (the previous behavior).
         Registered after the app bar so it opens below the full-width top bar. -->
    <v-navigation-drawer
      v-model="drawer"
      :temporary="!isRail"
      :permanent="isRail"
      :rail="isRail"
      :expand-on-hover="isRail"
      elevation="2"
    >
      <v-list
        nav
        density="comfortable"
      >
        <!-- Internal items use a client-side `to`; external ones (e.g. Horizon,
             a server-rendered page outside the SPA) use a real `href` opened in
             a new tab with an indicator icon. A divider precedes the first
             visible admin-section item, grouping the management tools. -->
        <template
          v-for="(item, i) in visibleNavItems"
          :key="item.titleKey"
        >
          <v-divider
            v-if="i === adminDividerIndex && i > 0"
            class="my-2"
          />
          <v-list-item
            :to="item.to"
            :href="item.href"
            :target="item.href ? '_blank' : undefined"
            :rel="item.href ? 'noopener' : undefined"
            :aria-label="item.href ? `${$t(item.titleKey)} (${$t('a11y.opensInNewTab')})` : undefined"
            :prepend-icon="item.icon"
            :append-icon="item.href ? 'mdi-open-in-new' : undefined"
            :title="$t(item.titleKey)"
            color="primary"
            rounded="lg"
          />
        </template>
      </v-list>

      <!-- Desktop-only pin toggle, pinned to the drawer bottom: switches between
           the temporary overlay and the permanent icon rail. Hidden on mobile,
           which is always an overlay. -->
      <template
        v-if="!isMobile"
        #append
      >
        <v-list
          nav
          density="comfortable"
        >
          <v-list-item
            :prepend-icon="railPinned ? 'mdi-pin-off-outline' : 'mdi-pin-outline'"
            :title="$t(railPinned ? 'nav.unpinMenu' : 'nav.pinMenu')"
            rounded="lg"
            @click="togglePin"
          />
        </v-list>
      </template>
    </v-navigation-drawer>

    <v-main>
      <!-- The layout owns the content shell: consistent page padding plus the
           page title and its breadcrumb trail, so pages only render their body. -->
      <v-container
        fluid
        class="pa-4 pa-md-6"
      >
        <!-- Page header block: the title, with any page-level actions right-
             aligned on the same line (a page's <AppPageHeader> teleports its
             buttons into #page-actions), the breadcrumb trail just beneath, and a
             consistent gap down to the page body. -->
        <div class="mb-6">
          <div class="d-flex flex-wrap align-center ga-4">
            <AppPageTitle />
            <v-spacer />
            <!-- Action cluster, right-aligned and flush with the content's right
                 edge (aligned with the cards below). The back button leads (the
                 conventional leftmost, least-prominent action) and #page-actions
                 (a page's teleported "New …" buttons) trails it. Grouping them in
                 one flex row means an empty #page-actions adds no trailing gap, so
                 the last visible control stays flush right. -->
            <div class="d-flex align-center ga-2">
              <!-- Back button for nested pages: navigates up to the breadcrumb
                   parent. Hidden on top-level pages. -->
              <v-btn
                v-if="parent?.to"
                prepend-icon="mdi-arrow-left"
                variant="tonal"
                @click="navigateTo(parent.to)"
              >
                {{ $t('common.back') }}
              </v-btn>
              <div
                id="page-actions"
                class="page-actions d-flex align-center ga-2"
              />
            </div>
          </div>
          <AppBreadcrumbTrail class="mt-1" />
        </div>
        <slot />
      </v-container>
    </v-main>
  </div>
</template>

<style scoped>
/* When a page teleports no actions into #page-actions, collapse it so the flex
   gap before it doesn't offset the leading control (the back button) from the
   content's right edge. A page's teleported buttons make it non-empty again. */
.page-actions:empty {
  display: none;
}
</style>
