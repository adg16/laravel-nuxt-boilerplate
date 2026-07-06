// https://nuxt.com/docs/api/configuration/nuxt-config
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@pinia/nuxt',
    '@nuxtjs/i18n',
    // Vuetify ships its own Vite plugin for per-component style/treeshaking;
    // register it via the vite:extendConfig hook (there's no first-party Nuxt
    // module that reliably supports Vuetify 4 yet — this is the documented
    // manual integration, paired with the plugin in app/plugins/vuetify.ts).
    (_options, nuxt) => {
      nuxt.hooks.hook('vite:extendConfig', (config) => {
        config.plugins!.push(vuetify({ autoImport: true }))
      })
    }
  ],

  // Internal backoffice SPA — no server-rendered HTML, nginx serves the
  // static build output directly in production (see docker/nginx).
  ssr: false,

  devtools: {
    enabled: true
  },

  runtimeConfig: {
    public: {
      // Relative by default: nginx fronts both the API and this SPA on the
      // same origin, so no absolute URL/CORS setup is needed in dev or prod.
      apiBase: '/api',
      // Displayed app name; override with NUXT_PUBLIC_APP_NAME (baked in at
      // build/generate time since this is a static SPA — no runtime server).
      appName: 'LarNux',
      // Short tagline shown under the brand; override with NUXT_PUBLIC_APP_TAGLINE.
      appTagline: 'Boilerplate for building Laravel + Nuxt apps'
    }
  },

  // Vuetify ships untranspiled ESM; Nuxt needs it in the transpile list.
  build: {
    transpile: ['vuetify']
  },

  compatibilityDate: '2026-06-30',

  vite: {
    vue: {
      template: {
        transformAssetUrls
      }
    }
  },

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  },

  // Internationalization. `no_prefix` keeps a single URL per page (no `/en`,
  // `/fr` segments) — right for an internal SPA. The active locale is detected
  // from the browser once, then persisted in a cookie. Locale messages live in
  // `i18n/locales/*.json`. To add a language: drop a new `<code>.json` next to
  // `en.json`, add an entry to `locales` below, and (for API messages) add it
  // to `supported_locales` in the backend's `config/app.php`.
  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'en',
    locales: [
      { code: 'en', name: 'English', language: 'en-US', file: 'en.json' }
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'i18n_locale'
    }
  }
})
