<script setup lang="ts">
// A UInput for passwords with a show/hide toggle. Forwards all attrs
// (placeholder, autocomplete, name, size, icon, required, class, ...) to the
// underlying UInput, so it's a drop-in replacement for `<UInput type="password">`.
const model = defineModel<string>()
const show = ref(false)

function toggle() {
  show.value = !show.value
}
</script>

<template>
  <UInput
    v-model="model"
    :type="show ? 'text' : 'password'"
    :ui="{ trailing: 'pe-1' }"
  >
    <template #trailing>
      <UButton
        type="button"
        color="neutral"
        variant="link"
        size="sm"
        :icon="show ? 'i-lucide-eye-off' : 'i-lucide-eye'"
        :aria-label="show ? 'Hide password' : 'Show password'"
        :aria-pressed="show"
        @click="toggle"
      />
    </template>
  </UInput>
</template>
