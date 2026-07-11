<script setup lang="ts">
// Confirm dialog. `type` drives the semantic accent color + leading icon +
// confirm-button color; the chrome is delegated to <AppDialogShell>. The parent
// runs the action in its `@confirm` handler and toggles `loading`.
//
//   <AppConfirmDialog
//     v-model="open"
//     type="error"
//     :title="$t('users.delete.title')"
//     :text="$t('users.delete.text', { name })"
//     :confirm-label="$t('common.delete')"
//     :loading="deleting"
//     @confirm="onDelete" />
type ConfirmType = 'info' | 'warning' | 'error' | 'success' | 'general'

const open = defineModel<boolean>({ required: true })

const props = withDefaults(defineProps<{
  title: string
  type?: ConfirmType
  text?: string
  confirmLabel?: string
  confirmColor?: string
  loading?: boolean
}>(), {
  type: 'general',
  text: undefined,
  confirmLabel: undefined,
  confirmColor: undefined,
  loading: false
})

defineEmits<{ confirm: [] }>()

// `general` maps to the brand primary; the rest to Vuetify's semantic theme
// colors so the accent, icon, and button all track the same token.
const typeColor = computed(() => ({
  info: 'info',
  warning: 'warning',
  error: 'error',
  success: 'success',
  general: 'primary'
}[props.type]))

const typeIcon = computed(() => ({
  info: 'mdi-information-outline',
  warning: 'mdi-alert-outline',
  error: 'mdi-alert-circle-outline',
  success: 'mdi-check-circle-outline',
  general: 'mdi-help-circle-outline'
}[props.type]))
</script>

<template>
  <AppDialogShell
    v-model="open"
    :title="title"
    :icon="typeIcon"
    :accent-color="typeColor"
    :max-width="480"
    :action-label="confirmLabel ?? $t('common.confirm')"
    :action-color="confirmColor ?? typeColor"
    :loading="loading"
    @action="$emit('confirm')"
  >
    <p
      v-if="text"
      class="text-body-medium text-medium-emphasis ma-0"
    >
      {{ text }}
    </p>
  </AppDialogShell>
</template>
