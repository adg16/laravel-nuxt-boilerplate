<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const { loading, error, submit } = useSubmit()
const sentMessage = ref('')

const formRef = ref<VForm>()
const state = reactive({
  email: ''
})

const emailRules = [zodRule(z.string().email('Enter a valid email address.'))]

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    sentMessage.value = await auth.forgotPassword(state.email)
  })
}
</script>

<template>
  <AuthCard
    title="Forgot your password?"
    subtitle="Enter your email and we'll send you a link to reset it."
  >
    <div
      v-if="sentMessage"
      class="d-flex flex-column ga-4"
    >
      <v-alert
        type="success"
        variant="tonal"
        icon="mdi-email-check-outline"
        :text="sentMessage"
      />
      <v-btn
        to="/login"
        variant="text"
        prepend-icon="mdi-arrow-left"
        block
      >
        Back to sign in
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
        v-model="state.email"
        type="email"
        label="Email"
        autocomplete="email"
        placeholder="you@example.com"
        prepend-inner-icon="mdi-email-outline"
        :rules="emailRules"
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
        Send reset link
      </v-btn>

      <NuxtLink
        to="/login"
        class="text-center text-body-2 text-medium-emphasis text-decoration-none"
      >
        Back to sign in
      </NuxtLink>
    </v-form>
  </AuthCard>
</template>
