<script setup lang="ts">
// An icon action button for data-table rows, with a tooltip. The tooltip is
// anchored to a wrapping span (not the button) so it still appears when the
// button is `disabled` — disabled buttons swallow pointer events, so a tooltip
// bound directly to them never shows. `tooltip` doubles as the aria-label.
withDefaults(defineProps<{
  icon: string
  tooltip: string
  color?: string
  disabled?: boolean
}>(), {
  color: undefined,
  disabled: false
})

defineEmits<{ click: [] }>()
</script>

<template>
  <v-tooltip
    :text="tooltip"
    location="top"
  >
    <template #activator="{ props }">
      <span v-bind="props">
        <v-btn
          :icon="icon"
          :color="color"
          :disabled="disabled"
          :aria-label="tooltip"
          variant="text"
          size="small"
          @click="$emit('click')"
        />
      </span>
    </template>
  </v-tooltip>
</template>
