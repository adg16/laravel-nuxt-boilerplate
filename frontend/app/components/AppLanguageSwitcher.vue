<script setup lang="ts">
// Locale picker for the app bar. Lists every configured locale and switches on
// click (the choice is persisted to a cookie by @nuxtjs/i18n). It only renders
// once there's more than one locale, so it stays hidden until a second language
// is added in nuxt.config — no dead single-item menu in the default setup.
const { locale, locales, setLocale } = useI18n()
</script>

<template>
  <v-menu
    v-if="locales.length > 1"
    location="bottom end"
  >
    <template #activator="{ props }">
      <v-btn
        v-bind="props"
        icon="mdi-translate"
        variant="text"
        :aria-label="$t('a11y.language')"
      />
    </template>

    <v-list
      width="180"
      density="comfortable"
    >
      <v-list-item
        v-for="item in locales"
        :key="item.code"
        :active="item.code === locale"
        :title="item.name"
        @click="setLocale(item.code)"
      />
    </v-list>
  </v-menu>
</template>
