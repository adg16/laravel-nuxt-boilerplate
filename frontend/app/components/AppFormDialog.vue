<script setup lang="ts">
// Create/edit form dialog. Owns the form semantics (saving state, submit label)
// and delegates the chrome to <AppDialogShell>. The parent supplies the form body
// via the default slot and keeps its own <v-form> ref for validate-on-submit.
//
//   <AppFormDialog
//     v-model="dialog"
//     :title="editing ? $t('users.edit') : $t('users.new')"
//     icon="mdi-account"
//     :saving="saving"
//     :submit-label="editing ? $t('common.save') : $t('users.create')"
//     @submit="onSubmit">
//     <v-form ref="formRef" validate-on="submit" @submit.prevent="onSubmit">…</v-form>
//   </AppFormDialog>
const open = defineModel<boolean>({ required: true })

withDefaults(defineProps<{
  title: string
  icon?: string
  maxWidth?: number | string
  saving?: boolean
  submitLabel?: string
  submitColor?: string
  submitDisabled?: boolean
}>(), {
  icon: undefined,
  maxWidth: 520,
  saving: false,
  submitLabel: undefined,
  submitColor: 'primary',
  submitDisabled: false
})

defineEmits<{ submit: [] }>()
</script>

<template>
  <AppDialogShell
    v-model="open"
    :title="title"
    :icon="icon"
    :max-width="maxWidth"
    :action-label="submitLabel ?? $t('common.save')"
    :action-color="submitColor"
    :loading="saving"
    :action-disabled="submitDisabled"
    @action="$emit('submit')"
  >
    <slot />
  </AppDialogShell>
</template>
