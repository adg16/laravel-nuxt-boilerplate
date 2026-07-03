export function useApi() {
  const config = useRuntimeConfig()

  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    onRequest({ options }) {
      // Laravel's CSRF middleware expects the XSRF-TOKEN cookie echoed back
      // as this header — unlike axios, $fetch doesn't do this automatically.
      const token = useCookie('XSRF-TOKEN').value
      if (token) {
        options.headers = new Headers(options.headers)
        options.headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
      }
    }
  })
}
