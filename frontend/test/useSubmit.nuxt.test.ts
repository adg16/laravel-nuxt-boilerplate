import { describe, it, expect } from 'vitest'

describe('useSubmit', () => {
  it('runs the action and leaves no error on success', async () => {
    const { loading, error, submit } = useSubmit()
    let ran = false

    await submit(async () => {
      ran = true
    })

    expect(ran).toBe(true)
    expect(error.value).toBe('')
    expect(loading.value).toBe(false)
  })

  it('surfaces the server-provided message on failure', async () => {
    const { error, submit } = useSubmit()

    await submit(async () => {
      throw { data: { message: 'Boom from server' } }
    })

    expect(error.value).toBe('Boom from server')
  })

  it('falls back to the given message when the error carries none', async () => {
    const { error, submit } = useSubmit()

    await submit(async () => {
      throw new Error('network down')
    }, 'Custom fallback')

    expect(error.value).toBe('Custom fallback')
  })

  it('routes a 422 validation bag to fieldErrors AND keeps the message on error', async () => {
    const { error, fieldErrors, submit } = useSubmit()

    await submit(async () => {
      throw { data: { message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } } }
    })

    // Inline for forms that bind fieldErrors...
    expect(fieldErrors.value.email).toEqual(['The email has already been taken.'])
    // ...and still on `error` so consumers that only render the alert (auth
    // pages) don't silently swallow the 422.
    expect(error.value).toBe('The email has already been taken.')
  })

  it('clears a field error on edit and resets all field errors on the next submit', async () => {
    const { fieldErrors, clearFieldError, submit } = useSubmit()

    await submit(async () => {
      throw { data: { errors: { email: ['taken'], name: ['required'] } } }
    })
    expect(fieldErrors.value.email).toEqual(['taken'])

    // Clearing one field empties its messages but keeps the key, so a
    // key-presence check (the alert gate) stays stable while it's fixed.
    clearFieldError('email')
    expect(fieldErrors.value.email).toEqual([])
    expect(fieldErrors.value.name).toEqual(['required'])
    expect(Object.keys(fieldErrors.value)).toContain('email')

    await submit(async () => {})
    expect(fieldErrors.value).toEqual({})
  })

  it('clearFieldError() with no field drops the whole bag (dialog reopen)', async () => {
    const { fieldErrors, clearFieldError, submit } = useSubmit()

    await submit(async () => {
      throw { data: { errors: { email: ['taken'], name: ['required'] } } }
    })
    expect(Object.keys(fieldErrors.value)).toHaveLength(2)

    clearFieldError()
    expect(fieldErrors.value).toEqual({})
  })
})
