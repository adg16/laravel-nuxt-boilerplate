<script setup lang="ts">
const { locale } = useI18n()

useHead({
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1' }
  ],
  link: [
    // SVG is theme-aware (adapts to dark mode); .ico is the legacy fallback.
    { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
    { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
    { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' }
  ],
  htmlAttrs: {
    // Track the active locale so assistive tech and the browser know the
    // document language.
    lang: locale
  }
})
</script>

<template>
  <v-app>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <AppSnackbar />
  </v-app>
</template>

<style>
/* Title-case every button label — but English only. CSS `capitalize` applies
   English-style word-casing, which mis-cases other locales (German nouns, French
   sentence case), and html[lang] tracks the active locale. Vuetify v4 renders
   button text as-is (`text-transform: none`); the doubled `.v-btn.v-btn` selector
   out-specifies its rule regardless of stylesheet order. Icon-only buttons have
   no text, so they're unaffected. */
:lang(en) .v-btn.v-btn {
  text-transform: capitalize;
}

/* Section-card headings (those inside a v-card-item — dialog titles aren't, so
   they keep their own size). MD3 has no token between title-large (22px) and
   title-medium (16px), so this sits one notch below title-large. */
.v-card-item .v-card-title {
  font-size: 1.125rem;
  line-height: 1.5rem;
}

/* Title-case those headings so subsections read as Title Case (e.g. "Change
   Password"). They're always i18n label text, never raw user data, so it's safe.
   English only, for the reason above. */
:lang(en) .v-card-item .v-card-title {
  text-transform: capitalize;
}
</style>
