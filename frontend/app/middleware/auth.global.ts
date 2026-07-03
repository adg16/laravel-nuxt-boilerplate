const PUBLIC_ROUTES = ['/login', '/forgot-password', '/reset-password']

export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const isPublic = PUBLIC_ROUTES.includes(to.path)

  if (!auth.user && !isPublic) {
    return navigateTo('/login')
  }

  if (auth.user && isPublic) {
    return navigateTo('/')
  }
})
