const PUBLIC_ROUTES = ['/login']

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
