export function useApi() {
  const config = useRuntimeConfig()

  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    onRequest({ options }) {
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
        options.headers = new Headers(options.headers)
        options.headers.set('X-XSRF-TOKEN', token)
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
