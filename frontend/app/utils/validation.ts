import type { ZodType } from 'zod'

// Adapts a Zod schema into a Vuetify field rule so validation messages stay
// single-sourced. Returns `true` when valid, else the first issue's message —
// exactly the shape v-text-field's `:rules` expects.
export function zodRule<T>(schema: ZodType<T>): (value: unknown) => true | string {
  return (value) => {
    const result = schema.safeParse(value)
    return result.success || (result.error.issues[0]?.message ?? 'Invalid value.')
  }
}
