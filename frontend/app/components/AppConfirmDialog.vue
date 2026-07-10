<script setup lang="ts">
// Generic confirm dialog for destructive actions. Open state is a v-model; the
// parent runs the action in its `@confirm` handler and toggles `loading`.
//
//   <AppConfirmDialog
//     v-model="open"
//     :title="$t('users.delete.title')"
//     :text="$t('users.delete.text', { name })"
//     :loading="deleting"
//     @confirm="onDelete" />
const open = defineModel<boolean>({ required: true })

withDefaults(defineProps<{
  title: string
  text?: string
  confirmLabel?: string
  confirmColor?: string
  loading?: boolean
}>(), {
  text: undefined,
  confirmLabel: undefined,
  confirmColor: 'error',
  loading: false
})

defineEmits<{ confirm: [] }>()
</script>

<template>
  <v-dialog
    v-model="open"
    max-width="480"
    :persistent="loading"
  >
    <v-card>
      <v-card-title class="text-title-large px-6 pt-6 pb-2">
        {{ title }}
      </v-card-title>
      <v-card-text
        v-if="text"
        class="text-body-medium text-medium-emphasis px-6 py-2"
      >
        {{ text }}
      </v-card-text>
      <v-card-actions class="px-6 pt-2 pb-6">
        <v-spacer />
        <v-btn
          variant="text"
          :disabled="loading"
          @click="open = false"
        >
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          :color="confirmColor"
          variant="flat"
          :loading="loading"
          @click="$emit('confirm')"
        >
          {{ confirmLabel ?? $t('common.confirm') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
