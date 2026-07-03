<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { loading, error, submit } = useSubmit()

const token = String(route.query.token ?? '')
const email = String(route.query.email ?? '')
const hasValidLink = computed(() => token !== '' && email !== '')

// Mirror the backend's Password::defaults() (min 8) so validation fails fast
// client-side instead of after a round-trip.
const schema = z.object({
  password: z.string().min(8, 'Password must be at least 8 characters.'),
  password_confirmation: z.string()
}).refine(data => data.password === data.password_confirmation, {
  message: 'Passwords do not match.',
  path: ['password_confirmation']
})
type Schema = z.output<typeof schema>

const state = reactive({
  password: '',
  password_confirmation: ''
})

async function onSubmit(event: FormSubmitEvent<Schema>) {
  await submit(async () => {
    await auth.resetPassword({
      token,
      email,
      password: event.data.password,
      password_confirmation: event.data.password_confirmation
    })
    toast.add({
      title: 'Password reset',
      description: 'Your password has been updated. Please sign in.',
      color: 'success',
      icon: 'i-lucide-circle-check'
    })
    router.push('/login')
  }, 'This reset link is invalid or has expired.')
}
</script>

<template>
  <UPageCard
    title="Reset your password"
    description="Choose a new password for your account."
  >
    <div
      v-if="!hasValidLink"
      class="flex flex-col gap-4"
    >
      <UAlert
        color="error"
        variant="subtle"
        icon="i-lucide-circle-alert"
        title="Invalid reset link"
        description="This link is missing information. Please request a new one."
      />
      <UButton
        to="/forgot-password"
        variant="ghost"
        color="neutral"
        icon="i-lucide-arrow-left"
        block
      >
        Request a new link
      </UButton>
    </div>

    <UForm
      v-else
      :schema="schema"
      :state="state"
      class="flex flex-col gap-4"
      @submit="onSubmit"
    >
      <UFormField label="Email">
        <UInput
          :model-value="email"
          type="email"
          icon="i-lucide-mail"
          size="lg"
          class="w-full"
          disabled
        />
      </UFormField>

      <UFormField
        label="New password"
        name="password"
      >
        <PasswordInput
          v-model="state.password"
          autocomplete="new-password"
          placeholder="••••••••"
          icon="i-lucide-lock"
          size="lg"
          class="w-full"
        />
      </UFormField>

      <UFormField
        label="Confirm password"
        name="password_confirmation"
      >
        <PasswordInput
          v-model="state.password_confirmation"
          autocomplete="new-password"
          placeholder="••••••••"
          icon="i-lucide-lock"
          size="lg"
          class="w-full"
        />
      </UFormField>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        icon="i-lucide-circle-alert"
        :title="error"
      />

      <UButton
        type="submit"
        size="lg"
        block
        :loading="loading"
      >
        Reset password
      </UButton>
    </UForm>
  </UPageCard>
</template>
