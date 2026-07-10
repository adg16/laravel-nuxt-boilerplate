const PUBLIC_ROUTES = ['/login', '/forgot-password', '/reset-password', '/accept-invite']

export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublic = PUBLIC_ROUTES.includes(to.path)

  if (!auth.user && !isPublic) {
    return navigateTo('/login')
  }

  if (auth.user && isPublic) {
    return navigateTo('/')
  }

  // Permission-gated pages (via `definePageMeta({ permission })`): bounce home
  // if the signed-in user lacks the ability, matching the hidden nav item and
  // the backend's 403.
  const permission = to.meta.permission
  if (auth.user && typeof permission === 'string' && !useAuthz().can(permission)) {
    return navigateTo('/')
  }

  // Required-mode 2FA: a signed-in user who hasn't enrolled is confined to the
  // Security page until they do (mirrors the backend's EnsureTwoFactorEnrolled
  // 403). The setup page itself must stay reachable.
  if (
    auth.user
    && !auth.user.two_factor_enabled
    && useConfigStore().twoFactorMode === 'required'
    && to.path !== '/security'
  ) {
    return navigateTo('/security')
  }
})
