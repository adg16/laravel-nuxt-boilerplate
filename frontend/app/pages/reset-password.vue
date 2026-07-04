<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
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
const passwordRules = [zodRule(z.string().min(8, 'Password must be at least 8 characters.'))]
const confirmationRules = [
  (v: string) => v === state.password || 'Passwords do not match.'
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
    notify('Your password has been updated. Please sign in.', 'success')
    router.push('/login')
  }, 'This reset link is invalid or has expired.')
}
</script>

<template>
  <AuthCard
    title="Reset your password"
    subtitle="Choose a new password for your account."
  >
    <div
      v-if="!hasValidLink"
      class="d-flex flex-column ga-4"
    >
      <v-alert
        type="error"
        variant="tonal"
        title="Invalid reset link"
        text="This link is missing information. Please request a new one."
      />
      <v-btn
        to="/forgot-password"
        variant="text"
        prepend-icon="mdi-arrow-left"
        block
      >
        Request a new link
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
        label="Email"
        prepend-inner-icon="mdi-email-outline"
        disabled
      />

      <PasswordInput
        v-model="state.password"
        label="New password"
        autocomplete="new-password"
        placeholder="••••••••"
        prepend-inner-icon="mdi-lock-outline"
        :rules="passwordRules"
      />

      <PasswordInput
        v-model="state.password_confirmation"
        label="Confirm password"
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
        Reset password
      </v-btn>
    </v-form>
  </AuthCard>
</template>
