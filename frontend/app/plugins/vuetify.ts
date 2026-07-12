import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

// Vuetify is registered manually (no Nuxt module) so we control the exact v4
// setup. `primary` drives the brand accent (buttons, links, the auth glow).
export default defineNuxtPlugin((nuxtApp) => {
  const vuetify = createVuetify({
    ssr: false,
    // App-wide component defaults: outlined inputs everywhere so individual
    // fields don't repeat `variant="outlined"`. `hideDetails: 'auto'` only
    // reserves the message row when there's actually an error/hint, which keeps
    // form spacing even (fields don't leave a permanent blank line beneath).
    defaults: {
      // Cards sit slightly raised off the grey app background. Cards that opt out
      // with `flat` (e.g. nested panels) keep elevation 0.
      VCard: { elevation: 1 },
      VTextField: { variant: 'outlined', hideDetails: 'auto' },
      VTextarea: { variant: 'outlined', hideDetails: 'auto' },
      VSelect: { variant: 'outlined', hideDetails: 'auto' },
      VAutocomplete: { variant: 'outlined', hideDetails: 'auto' },
      VCombobox: { variant: 'outlined', hideDetails: 'auto' }
    },
    icons: {
      defaultSet: 'mdi',
      aliases,
      sets: { mdi }
    },
    theme: {
      defaultTheme: 'light',
      themes: {
        light: {
          dark: false,
          colors: {
            // A soft grey app background so the white (`surface`) cards read as
            // raised cards. Dark theme already contrasts (background #121212 vs
            // surface #212121), so only light needs an override.
            background: '#F4F4F5',
            primary: '#EA580C',
            secondary: '#7C2D12'
          }
        },
        dark: {
          dark: true,
          colors: {
            primary: '#FB923C',
            secondary: '#FDBA74'
          }
        }
      }
    }
  })

  nuxtApp.vueApp.use(vuetify)
})
