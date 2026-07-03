import { defineVitestConfig } from '@nuxt/test-utils/config'

// Component/composable tests run in the Nuxt runtime environment so auto-imports
// (ref, useState, Nuxt UI components, project composables) resolve as they do in
// the app. See test/*.nuxt.test.ts.
export default defineVitestConfig({
  test: {
    environment: 'nuxt'
  }
})
