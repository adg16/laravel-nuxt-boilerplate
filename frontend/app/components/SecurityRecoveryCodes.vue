<script setup lang="ts">
// Displays the one-time recovery codes for a 2FA setup, with a copy-all button.
// Shown during enrollment and when revealed/regenerated on the Security page.
const props = defineProps<{ codes: string[] }>()

const { notify } = useSnackbar()
const { t } = useI18n()

async function copyAll() {
  try {
    await navigator.clipboard.writeText(props.codes.join('\n'))
    notify(t('security.recoveryCopied'))
  } catch {
    notify(t('common.genericError'), 'error')
  }
}
</script>

<template>
  <v-sheet
    border
    rounded="lg"
    color="surface-light"
    class="pa-4"
  >
    <div class="d-flex align-center ga-2 mb-3">
      <v-icon
        icon="mdi-key-chain-variant"
        size="20"
      />
      <span class="text-title-small">{{ $t('security.recoveryCodesTitle') }}</span>
      <v-spacer />
      <v-btn
        variant="text"
        size="small"
        prepend-icon="mdi-content-copy"
        @click="copyAll"
      >
        {{ $t('common.copy') }}
      </v-btn>
    </div>
    <div class="recovery-grid text-body-medium">
      <code
        v-for="code in codes"
        :key="code"
      >{{ code }}</code>
    </div>
    <p class="text-body-small text-medium-emphasis mt-3 mb-0">
      {{ $t('security.recoveryCodesHint') }}
    </p>
  </v-sheet>
</template>

<style scoped>
.recovery-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.25rem 1.5rem;
}
</style>
