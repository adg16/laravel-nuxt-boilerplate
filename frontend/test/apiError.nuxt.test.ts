import { describe, it, expect } from 'vitest'

// apiErrorMessage is auto-imported from app/utils in the Nuxt runtime.

describe('apiErrorMessage', () => {
  it('maps a 429 to the friendly rate-limit message', () => {
    expect(apiErrorMessage({ status: 429 })).toBe('Too many attempts. Please try again later.')
  })

  it('includes the retry time when a Retry-After header is present', () => {
    const error = {
      status: 429,
      response: { headers: { get: (name: string) => (name === 'retry-after' ? '30' : null) } }
    }
    expect(apiErrorMessage(error)).toBe('Too many attempts. Please try again in 30 seconds.')
  })

  it('prefers the server-provided message for other errors', () => {
    expect(apiErrorMessage({ status: 422, data: { message: 'The email is invalid.' } }))
      .toBe('The email is invalid.')
  })

  it('falls back to the provided fallback, then the generic message', () => {
    expect(apiErrorMessage({ status: 500 }, 'Custom fallback')).toBe('Custom fallback')
    expect(apiErrorMessage({})).toBe('Something went wrong. Please try again.')
  })
})
