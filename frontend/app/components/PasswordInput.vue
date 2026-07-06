<script setup lang="ts">
// A v-text-field for passwords with a show/hide toggle. Forwards all attrs
// (placeholder, autocomplete, name, prepend-inner-icon, density, ...) to the
// underlying v-text-field, so it's a drop-in replacement for a password field.
const model = defineModel<string>()
const show = ref(false)

function toggle() {
  show.value = !show.value
}
</script>

<template>
  <v-text-field
    v-model="model"
    :type="show ? 'text' : 'password'"
  >
    <template #append-inner>
      <v-btn
        type="button"
        variant="text"
        size="small"
        density="comfortable"
        :icon="show ? 'mdi-eye-off' : 'mdi-eye'"
        :aria-label="show ? $t('a11y.hidePassword') : $t('a11y.showPassword')"
        :aria-pressed="show"
        @click="toggle"
      />
    </template>
  </v-text-field>
</template>
