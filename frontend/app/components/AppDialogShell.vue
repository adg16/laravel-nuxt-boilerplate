<script setup lang="ts">
// Shared chrome for the app's modal dialogs: a v-dialog + v-card with a colored
// accent line under the title, an optional leading icon, a padded body slot, and
// a Cancel + primary-action row separated by a neutral divider. The thin wrappers
// <AppFormDialog> and <AppConfirmDialog> supply the semantics; this owns the look
// so it stays consistent in one place.
const open = defineModel<boolean>({ required: true })

withDefaults(defineProps<{
  title: string
  actionLabel: string
  icon?: string
  accentColor?: string
  actionColor?: string
  maxWidth?: number | string
  loading?: boolean
  actionDisabled?: boolean
}>(), {
  icon: undefined,
  accentColor: 'primary',
  actionColor: 'primary',
  maxWidth: 520,
  loading: false,
  actionDisabled: false
})

defineEmits<{ action: [] }>()
</script>

<template>
  <v-dialog
    v-model="open"
    :max-width="maxWidth"
    :persistent="loading"
  >
    <v-card>
      <v-card-title
        class="app-dialog-shell__title d-flex align-center ga-3 text-title-large px-6 pt-6 pb-3 mb-4"
        :style="{ borderBottomColor: `rgb(var(--v-theme-${accentColor}))` }"
      >
        <v-icon
          v-if="icon"
          :icon="icon"
          :color="accentColor"
          size="24"
        />
        <span>{{ title }}</span>
      </v-card-title>
      <v-card-text class="px-6 py-2">
        <slot />
      </v-card-text>
      <v-card-actions class="app-dialog-shell__actions px-6 pt-4 pb-6 mt-4">
        <v-spacer />
        <v-btn
          variant="tonal"
          :disabled="loading"
          @click="open = false"
        >
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          :color="actionColor"
          variant="flat"
          :loading="loading"
          :disabled="actionDisabled"
          @click="$emit('action')"
        >
          {{ actionLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
/* Accent line under the title; its color comes from `accentColor` (set inline). */
.app-dialog-shell__title {
  border-bottom: 1px solid;
}

/* Title-case the title in English only. CSS `capitalize` applies English-style
   word-casing, which is wrong for other locales (German nouns, French sentence
   case); html[lang] tracks the active locale, so this scopes cleanly. */
:lang(en) .app-dialog-shell__title {
  text-transform: capitalize;
}

/* Neutral divider before the actions — a subtle grey in both themes via
   Vuetify's border token. */
.app-dialog-shell__actions {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
