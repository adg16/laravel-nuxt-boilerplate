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
})
