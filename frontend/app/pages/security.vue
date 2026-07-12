<script setup lang="ts">
// Self-service two-factor management for the signed-in user (the app's only
// self-service surface). Supports two methods — a TOTP authenticator app and
// emailed one-time codes — with the admin's `two_factor_methods` policy deciding
// which the user may enroll. States: disabled → enrolling (a pending setup
// awaiting confirmation) → enabled.
definePageMeta({
  breadcrumb: 'nav.security'
})

const auth = useAuthStore()
const config = useConfigStore()
const tf = useTwoFactor()
const { notify } = useSnackbar()
const { t } = useI18n()

const enabled = computed(() => auth.user?.two_factor_enabled ?? false)
const modeOff = computed(() => config.twoFactorMode === 'off')
const modeRequired = computed(() => config.twoFactorMode === 'required')

// Which methods the policy permits, and the one being set up.
const policy = computed(() => config.twoFactorMethods)
const canChooseMethod = computed(() => policy.value === 'both')
const chosenMethod = ref<'totp' | 'email'>('totp')
const effectiveMethod = computed<'totp' | 'email'>(() =>
  policy.value === 'both' ? chosenMethod.value : (policy.value === 'email' ? 'email' : 'totp')
)

const enrolling = ref(false)
const enrollMethod = ref<'totp' | 'email'>('totp')
const busy = ref(false)
const confirming = ref(false)

const qrSvg = ref('')
const secretKey = ref('')
const recoveryCodes = ref<string[]>([])
const showRecovery = ref(false)
const confirmCode = ref('')
const codeError = ref('')

const isEmailEnroll = computed(() => enrollMethod.value === 'email')

// Render the Fortify-generated SVG as an <img> data URI (avoids v-html). The
// QR markup is ASCII, so btoa is safe.
const qrDataUri = computed(() =>
  qrSvg.value ? `data:image/svg+xml;base64,${btoa(qrSvg.value)}` : ''
)

// The enabled-state subtitle: names the active method when known, else a
// generic "enabled" line (a confirmed setup with no recorded method predates
// the two_factor_method column — new enrollments always set it).
const enabledSubtitle = computed(() =>
  auth.user?.two_factor_method
    ? t('security.card.enabledVia', { method: t(`security.methodLabel.${auth.user.two_factor_method}`) })
    : t('security.card.enabled')
)

// Switching methods (only when the policy lets users choose): re-enroll the
// *other* method. Replace-immediately — the current method is torn down as soon
// as enrollment starts, so the user must confirm the new one to be protected
// again (the confirm dialog warns of this).
const activeMethod = computed<'totp' | 'email' | null>(() => auth.user?.two_factor_method ?? null)
const otherMethod = computed<'totp' | 'email'>(() => (activeMethod.value === 'email' ? 'totp' : 'email'))
const changeDialog = ref(false)

async function changeMethod() {
  changeDialog.value = false
  chosenMethod.value = otherMethod.value
  await startEnroll()
}

async function startEnroll() {
  busy.value = true
  const m = effectiveMethod.value
  try {
    if (m === 'email') {
      const { recovery_codes } = await tf.enableEmail()
      recoveryCodes.value = recovery_codes
    } else {
      await tf.enable()
      const [qr, secret, codes] = await Promise.all([tf.qrCode(), tf.secretKey(), tf.recoveryCodes()])
      qrSvg.value = qr.svg
      secretKey.value = secret.secretKey
      recoveryCodes.value = codes
    }
    enrollMethod.value = m
    enrolling.value = true
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
    // The enrollment may have partially applied server-side before failing —
    // e.g. a method *switch* tears down the old factor in tf.enable()/enableEmail()
    // before the QR/secret fetch — so re-sync the user rather than leave the UI
    // showing a stale "enabled" state that no longer matches the backend.
    await auth.fetchUser()
  } finally {
    busy.value = false
  }
}

async function confirm() {
  codeError.value = ''
  confirming.value = true
  try {
    await (isEmailEnroll.value ? tf.confirmEmail(confirmCode.value) : tf.confirm(confirmCode.value))
    await auth.fetchUser()
    enrolling.value = false
    confirmCode.value = ''
    showRecovery.value = true // keep the codes visible on the enabled screen
    notify(t('security.enabledToast'))
  } catch (e) {
    const err = e as { data?: { errors?: { code?: string[] } } }
    codeError.value = err.data?.errors?.code?.[0] ?? apiErrorMessage(e, t('auth.twoFactor.invalid'))
  } finally {
    confirming.value = false
  }
}

async function resendEnrollCode() {
  try {
    await tf.resendEmailEnroll()
    notify(t('security.email.resent'))
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

function resetEnroll() {
  enrolling.value = false
  qrSvg.value = ''
  secretKey.value = ''
  recoveryCodes.value = []
  confirmCode.value = ''
  codeError.value = ''
}

// Cancelling a half-finished enrollment removes the pending (unconfirmed) setup
// so it doesn't linger on the account. Re-fetch the user afterwards: cancelling
// a *method switch* leaves 2FA off (the old method was already torn down), and
// the UI must reflect that.
async function cancelEnroll() {
  busy.value = true
  try {
    await tf.disable()
  } catch {
    // Best-effort teardown; reset the UI regardless.
  } finally {
    resetEnroll()
    await auth.fetchUser()
    busy.value = false
  }
}

async function disable() {
  busy.value = true
  try {
    await tf.disable()
    await auth.fetchUser()
    resetEnroll()
    showRecovery.value = false
    notify(t('security.disabledToast'))
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    busy.value = false
  }
}

async function revealRecovery() {
  try {
    recoveryCodes.value = await tf.recoveryCodes()
    showRecovery.value = true
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

async function regenerate() {
  busy.value = true
  try {
    recoveryCodes.value = await tf.regenerateRecoveryCodes()
    showRecovery.value = true
    notify(t('security.recoveryRegenerated'))
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div>
    <!-- Required mode: enrollment is mandatory before the rest of the app opens. -->
    <v-alert
      v-if="modeRequired && !enabled"
      type="warning"
      variant="tonal"
      density="comfortable"
      class="mb-6"
      :text="$t('security.requiredHint')"
    />

    <v-card
      border
    >
      <v-card-item>
        <template #prepend>
          <v-icon
            :icon="enabled ? 'mdi-shield-check' : 'mdi-shield-off-outline'"
            :color="enabled ? 'success' : undefined"
            size="28"
          />
        </template>
        <v-card-title>
          {{ $t('security.card.title') }}
        </v-card-title>
        <v-card-subtitle>
          {{ enabled ? enabledSubtitle : $t('security.card.disabled') }}
        </v-card-subtitle>
      </v-card-item>

      <v-divider />

      <v-card-text>
        <!-- Disabled + admin turned 2FA off: nothing to do here. -->
        <div
          v-if="!enabled && !enrolling && modeOff"
          class="text-body-medium text-medium-emphasis"
        >
          {{ $t('security.unavailable') }}
        </div>

        <!-- Disabled, enrollment available: choose a method (if allowed) + start. -->
        <div
          v-else-if="!enabled && !enrolling"
          class="d-flex flex-column ga-4"
        >
          <p class="text-body-medium text-medium-emphasis mb-0">
            {{ $t('security.enableIntro') }}
          </p>

          <div v-if="canChooseMethod">
            <div class="text-label-large text-medium-emphasis mb-1">
              {{ $t('security.method.title') }}
            </div>
            <v-btn-toggle
              v-model="chosenMethod"
              color="primary"
              variant="outlined"
              density="comfortable"
              divided
              mandatory
            >
              <v-btn
                value="totp"
                prepend-icon="mdi-cellphone-key"
              >
                {{ $t('security.methodLabel.totp') }}
              </v-btn>
              <v-btn
                value="email"
                prepend-icon="mdi-email-outline"
              >
                {{ $t('security.methodLabel.email') }}
              </v-btn>
            </v-btn-toggle>
            <p class="text-body-small text-medium-emphasis mt-2 mb-0">
              {{ effectiveMethod === 'email' ? $t('security.method.emailHint') : $t('security.method.totpHint') }}
            </p>
          </div>

          <div>
            <v-btn
              color="primary"
              variant="flat"
              :loading="busy"
              prepend-icon="mdi-shield-plus-outline"
              @click="startEnroll"
            >
              {{ $t('security.enable') }}
            </v-btn>
          </div>
        </div>

        <!-- Enrolling: method-specific setup, then confirm a code. -->
        <div
          v-else-if="enrolling"
          class="d-flex flex-column ga-6"
        >
          <!-- TOTP: scan the QR / enter the key. -->
          <div v-if="!isEmailEnroll">
            <div class="text-title-small mb-1">
              {{ $t('security.scanTitle') }}
            </div>
            <p class="text-body-small text-medium-emphasis mb-3">
              {{ $t('security.scanHint') }}
            </p>
            <div class="d-flex flex-wrap align-center ga-4">
              <v-sheet
                border
                rounded="lg"
                class="pa-2 d-inline-flex"
              >
                <img
                  :src="qrDataUri"
                  :alt="$t('security.qrAlt')"
                  width="180"
                  height="180"
                >
              </v-sheet>
              <div>
                <div class="text-body-small text-medium-emphasis mb-1">
                  {{ $t('security.secretLabel') }}
                </div>
                <code class="text-body-medium">{{ secretKey }}</code>
              </div>
            </div>
          </div>

          <!-- Email: a code was sent to the address. -->
          <div v-else>
            <div class="text-title-small mb-1">
              {{ $t('security.email.enrollTitle') }}
            </div>
            <p class="text-body-small text-medium-emphasis mb-0">
              {{ $t('security.email.enrollHint', { email: auth.user?.email }) }}
            </p>
          </div>

          <SecurityRecoveryCodes :codes="recoveryCodes" />

          <v-form
            class="d-flex flex-column ga-3"
            style="max-width: 320px"
            @submit.prevent="confirm"
          >
            <div class="text-title-small">
              {{ $t('security.confirmTitle') }}
            </div>
            <v-text-field
              v-model="confirmCode"
              :label="$t('auth.twoFactor.codeLabel')"
              :error-messages="codeError"
              autocomplete="one-time-code"
              inputmode="numeric"
              autofocus
            />
            <div class="d-flex flex-wrap ga-3">
              <v-btn
                type="submit"
                color="primary"
                variant="flat"
                :loading="confirming"
              >
                {{ $t('security.confirm') }}
              </v-btn>
              <v-btn
                v-if="isEmailEnroll"
                variant="text"
                :disabled="confirming"
                @click="resendEnrollCode"
              >
                {{ $t('security.email.resend') }}
              </v-btn>
              <v-btn
                variant="text"
                :disabled="confirming || busy"
                @click="cancelEnroll"
              >
                {{ $t('common.cancel') }}
              </v-btn>
            </div>
          </v-form>
        </div>

        <!-- Enabled: manage recovery codes, or disable. -->
        <div
          v-else
          class="d-flex flex-column ga-6"
        >
          <div>
            <div class="text-title-small mb-1">
              {{ $t('security.recoveryTitle') }}
            </div>
            <p class="text-body-small text-medium-emphasis mb-3">
              {{ $t('security.recoveryHint') }}
            </p>

            <SecurityRecoveryCodes
              v-if="showRecovery"
              :codes="recoveryCodes"
              class="mb-3"
            />

            <div class="d-flex flex-wrap ga-3">
              <v-btn
                v-if="!showRecovery"
                variant="outlined"
                prepend-icon="mdi-eye-outline"
                @click="revealRecovery"
              >
                {{ $t('security.showRecovery') }}
              </v-btn>
              <v-btn
                variant="outlined"
                prepend-icon="mdi-refresh"
                :loading="busy"
                @click="regenerate"
              >
                {{ $t('security.regenerate') }}
              </v-btn>
            </div>
          </div>

          <!-- Change method — only when the policy lets users choose. -->
          <template v-if="canChooseMethod">
            <v-divider />
            <div>
              <div class="text-title-small mb-1">
                {{ $t('security.changeMethod.title') }}
              </div>
              <p class="text-body-small text-medium-emphasis mb-3">
                {{ $t('security.changeMethod.hint') }}
              </p>
              <v-btn
                variant="outlined"
                prepend-icon="mdi-swap-horizontal"
                :disabled="busy"
                @click="changeDialog = true"
              >
                {{ $t('security.changeMethod.switchTo', { method: $t(`security.methodLabel.${otherMethod}`) }) }}
              </v-btn>
            </div>
          </template>

          <v-divider />

          <div>
            <v-btn
              color="error"
              variant="outlined"
              prepend-icon="mdi-shield-off-outline"
              :loading="busy"
              :disabled="modeRequired"
              @click="disable"
            >
              {{ $t('security.disable') }}
            </v-btn>
            <p
              v-if="modeRequired"
              class="text-body-small text-medium-emphasis mt-2 mb-0"
            >
              {{ $t('security.disableBlocked') }}
            </p>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <AppConfirmDialog
      v-model="changeDialog"
      type="info"
      :title="$t('security.changeMethod.confirmTitle')"
      :text="$t('security.changeMethod.confirmText', { method: $t(`security.methodLabel.${otherMethod}`) })"
      :confirm-label="$t('security.changeMethod.confirmLabel')"
      @confirm="changeMethod"
    />
  </div>
</template>
