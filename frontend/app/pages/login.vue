<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()
const { loading, error, submit } = useSubmit()

const formRef = ref<VForm>()
const state = reactive({
  email: '',
  password: ''
})

const emailRules = [zodRule(z.string().email('Enter a valid email address.'))]
const passwordRules = [zodRule(z.string().min(1, 'Password is required.'))]

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    await auth.login(state)
    router.push('/')
  }, 'Invalid credentials.')
}
</script>

<template>
  <AuthCard
    title="Sign in"
    subtitle="Sign in to your account to continue."
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
        label="Email"
        autocomplete="email"
        placeholder="you@example.com"
        prepend-inner-icon="mdi-email-outline"
        :rules="emailRules"
      />

      <div>
        <PasswordInput
          v-model="state.password"
          label="Password"
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
            Forgot password?
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
        Sign in
      </v-btn>
    </v-form>
  </AuthCard>
</template>
