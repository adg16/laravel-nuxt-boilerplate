// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    files: ['app/components/Can.vue'],
    rules: {
      // `<Can permission="...">` is intentionally a single, verb-like guard
      // component — the terse name is the point of its API.
      'vue/multi-word-component-names': 'off'
    }
  }
)
