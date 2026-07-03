import { describe, it, expect, beforeEach } from 'vitest'
import { readCookie } from '~/composables/useApi'

// readCookie is the CSRF-token source useApi's onRequest feeds into the
// X-XSRF-TOKEN header. The regression it guards: reading FRESH from
// document.cookie each call (not via cached useCookie) so a token Sanctum
// rotates on login is picked up on the next request — otherwise logout 419s.
function clearCookies() {
  for (const c of document.cookie.split(';')) {
    document.cookie = `${c.split('=')[0].trim()}=; expires=Thu, 01 Jan 1970 00:00:00 GMT`
  }
}

describe('readCookie (useApi CSRF source)', () => {
  beforeEach(() => clearCookies())

  it('reads and url-decodes the current cookie value', () => {
    // As Laravel sets it: base64 padding = becomes %3D.
    document.cookie = 'XSRF-TOKEN=rotated-token%3D%3D'

    expect(readCookie('XSRF-TOKEN')).toBe('rotated-token==')
  })

  it('reflects a rotated value on the next read — no caching', () => {
    document.cookie = 'XSRF-TOKEN=first-token'
    expect(readCookie('XSRF-TOKEN')).toBe('first-token')

    document.cookie = 'XSRF-TOKEN=second-token'
    expect(readCookie('XSRF-TOKEN')).toBe('second-token')
  })

  it('returns null when the cookie is absent', () => {
    expect(readCookie('XSRF-TOKEN')).toBeNull()
  })

  it('does not match a differently-named cookie', () => {
    document.cookie = 'OTHER-XSRF-TOKEN=nope'

    expect(readCookie('XSRF-TOKEN')).toBeNull()
  })
})
