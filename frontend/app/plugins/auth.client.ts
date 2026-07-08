export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()
  await auth.fetchUser()

  // Load UI-shaping app config once the user is known (it needs a session).
  if (auth.user) {
    await useConfigStore().fetch()
  }
})
