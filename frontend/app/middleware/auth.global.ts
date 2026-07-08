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
})
