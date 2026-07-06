export function useApi() {
  const config = useRuntimeConfig()
  const { $i18n } = useNuxtApp()

  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    onRequest({ options }) {
      options.headers = new Headers(options.headers)

      // Laravel's CSRF middleware expects the XSRF-TOKEN cookie echoed back as
      // this header — unlike axios, $fetch doesn't do this automatically.
      //
      // Read it fresh from document.cookie on every request rather than via
      // useCookie(): Sanctum rotates the token whenever the session regenerates
      // (e.g. on login), and useCookie caches the value it read at call time, so
      // it would keep sending the pre-login token — making the next POST
      // (typically logout) fail with a 419 CSRF mismatch.
      const token = readCookie('XSRF-TOKEN')
      if (token) {
        options.headers.set('X-XSRF-TOKEN', token)
      }

      // Advertise the active locale so the API can localize its responses
      // (validation/auth messages) via the backend's SetLocale middleware.
      const locale = $i18n?.locale?.value
      if (locale) {
        options.headers.set('Accept-Language', locale)
      }
    }
  })
}

// Exported for unit testing — reads the cookie FRESH from document.cookie on
// every call (unlike useCookie, which caches) so a token rotated by Sanctum on
// login is picked up on the next request.
export function readCookie(name: string): string | null {
  if (!import.meta.client) {
    return null
  }
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return match?.[1] ? decodeURIComponent(match[1]) : null
}
