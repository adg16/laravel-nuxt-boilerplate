<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { notify } = useSnackbar()
const { loading, error, submit } = useSubmit()

const token = String(route.query.token ?? '')
const email = String(route.query.email ?? '')
const hasValidLink = computed(() => token !== '' && email !== '')

const formRef = ref<VForm>()
const state = reactive({
  password: '',
  password_confirmation: ''
})

// Mirror the backend's Password::defaults() (min 8) so validation fails fast
// client-side instead of after a round-trip.
const passwordRules = [zodRule(z.string().min(8, t('validation.passwordMin')))]
const confirmationRules = [
  (v: string) => v === state.password || t('validation.passwordsNoMatch')
]

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    await auth.resetPassword({
      token,
      email,
      password: state.password,
      password_confirmation: state.password_confirmation
    })
    notify(t('auth.reset.success'), 'success')
    router.push('/login')
  }, t('auth.reset.expired'))
}
</script>

<template>
  <AuthCard
    :title="$t('auth.reset.title')"
    :subtitle="$t('auth.reset.subtitle')"
  >
    <div
      v-if="!hasValidLink"
      class="d-flex flex-column ga-4"
    >
      <v-alert
        type="error"
        variant="tonal"
        :title="$t('auth.reset.invalidLinkTitle')"
        :text="$t('auth.reset.invalidLinkText')"
      />
      <v-btn
        to="/forgot-password"
        variant="text"
        prepend-icon="mdi-arrow-left"
        block
      >
        {{ $t('auth.reset.requestNew') }}
      </v-btn>
    </div>

    <v-form
      v-else
      ref="formRef"
      validate-on="submit"
      class="d-flex flex-column ga-4"
      @submit.prevent="onSubmit"
    >
      <v-text-field
        :model-value="email"
        type="email"
        :label="$t('fields.email')"
        prepend-inner-icon="mdi-email-outline"
        disabled
      />

      <PasswordInput
        v-model="state.password"
        :label="$t('fields.newPassword')"
        autocomplete="new-password"
        placeholder="••••••••"
        prepend-inner-icon="mdi-lock-outline"
        :rules="passwordRules"
      />

      <PasswordInput
        v-model="state.password_confirmation"
        :label="$t('fields.confirmPassword')"
        autocomplete="new-password"
        placeholder="••••••••"
        prepend-inner-icon="mdi-lock-outline"
        :rules="confirmationRules"
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
        {{ $t('auth.reset.submit') }}
      </v-btn>
    </v-form>
  </AuthCard>
</template>
