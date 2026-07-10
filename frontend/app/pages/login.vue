<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()
const { loading, error, submit } = useSubmit()

const formRef = ref<VForm>()
const state = reactive({
  email: '',
  password: ''
})

// Login is one or two steps: after valid credentials, a 2FA-enabled account
// switches to the `challenge` step. The method (TOTP authenticator vs emailed
// code) decides which copy/inputs show.
const step = ref<'credentials' | 'challenge'>('credentials')
const method = ref<'totp' | 'email'>('totp')
const useRecoveryCode = ref(false)
const challenge = reactive({
  code: '',
  recovery_code: ''
})
const { notify } = useSnackbar()
const resending = ref(false)

const isEmail = computed(() => method.value === 'email')

const emailRules = [zodRule(z.string().email(t('validation.email')))]
const passwordRules = [zodRule(z.string().min(1, t('validation.passwordRequired')))]
const codeRules = [zodRule(z.string().min(1, t('validation.required')))]

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    const result = await auth.login(state)
    if (result.twoFactor) {
      method.value = result.method
      step.value = 'challenge'
      return
    }
    router.push('/')
  }, t('auth.login.invalid'))
}

async function onChallenge() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  const payload = useRecoveryCode.value
    ? { recovery_code: challenge.recovery_code }
    : { code: challenge.code }

  await submit(async () => {
    await (isEmail.value ? auth.emailChallenge(payload) : auth.twoFactorChallenge(payload))
    router.push('/')
  }, t('auth.twoFactor.invalid'))
}

// Email only: request a fresh code.
async function onResend() {
  resending.value = true
  try {
    notify(await auth.resendEmailChallenge())
  } catch {
    notify(t('common.genericError'), 'error')
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <AuthCard
    v-if="step === 'credentials'"
    :title="$t('auth.login.title')"
    :subtitle="$t('auth.login.subtitle')"
  >
    <v-form
      ref="formRef"
      validate-on="submit"
      class="d-flex flex-column ga-4"
      @submit.prevent="onSubmit"
    >
      <v-text-field
        v-model="state.email"
        type="email"
        :label="$t('fields.email')"
        autocomplete="email"
        placeholder="you@example.com"
        prepend-inner-icon="mdi-email-outline"
        :rules="emailRules"
      />

      <div>
        <PasswordInput
          v-model="state.password"
          :label="$t('fields.password')"
          autocomplete="current-password"
          placeholder="••••••••"
          prepend-inner-icon="mdi-lock-outline"
          :rules="passwordRules"
        />
        <div class="d-flex justify-end mt-1">
          <NuxtLink
            to="/forgot-password"
            class="text-body-small text-primary text-decoration-none font-weight-medium"
          >
            {{ $t('auth.login.forgot') }}
          </NuxtLink>
        </div>
      </div>

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
        density="comfortable"
        :text="error"
      />

      <v-btn
        type="submit"
        color="primary"
        size="large"
        block
        :loading="loading"
      >
        {{ $t('auth.login.submit') }}
      </v-btn>
    </v-form>
  </AuthCard>

  <AuthCard
    v-else
    :title="$t('auth.twoFactor.title')"
    :subtitle="useRecoveryCode
      ? $t('auth.twoFactor.recoverySubtitle')
      : (isEmail ? $t('auth.twoFactor.email.subtitle') : $t('auth.twoFactor.subtitle'))"
  >
    <v-form
      ref="formRef"
      validate-on="submit"
      class="d-flex flex-column ga-4"
      @submit.prevent="onChallenge"
    >
      <v-text-field
        v-if="!useRecoveryCode"
        v-model="challenge.code"
        :label="$t('auth.twoFactor.codeLabel')"
        autocomplete="one-time-code"
        inputmode="numeric"
        autofocus
        :prepend-inner-icon="isEmail ? 'mdi-email-outline' : 'mdi-shield-key-outline'"
        :rules="codeRules"
      />
      <v-text-field
        v-else
        v-model="challenge.recovery_code"
        :label="$t('auth.twoFactor.recoveryLabel')"
        autocomplete="one-time-code"
        autofocus
        prepend-inner-icon="mdi-key-outline"
        :rules="codeRules"
      />

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
        density="comfortable"
        :text="error"
      />

      <v-btn
        type="submit"
        color="primary"
        size="large"
        block
        :loading="loading"
      >
        {{ $t('auth.twoFactor.submit') }}
      </v-btn>

      <div class="d-flex flex-column align-center ga-1">
        <v-btn
          v-if="isEmail && !useRecoveryCode"
          type="button"
          variant="text"
          size="small"
          color="primary"
          class="text-body-small"
          :loading="resending"
          @click="onResend"
        >
          {{ $t('auth.twoFactor.email.resend') }}
        </v-btn>
        <v-btn
          type="button"
          variant="text"
          size="small"
          color="primary"
          class="text-body-small"
          @click="useRecoveryCode = !useRecoveryCode"
        >
          {{ useRecoveryCode ? $t('auth.twoFactor.useCode') : $t('auth.twoFactor.useRecovery') }}
        </v-btn>
      </div>
    </v-form>
  </AuthCard>
</template>
