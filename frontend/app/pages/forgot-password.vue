<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const { loading, error, submit } = useSubmit()
const sentMessage = ref('')

const schema = z.object({
  email: z.string().email('Enter a valid email address.')
})
type Schema = z.output<typeof schema>

const state = reactive({
  email: ''
})

async function onSubmit(event: FormSubmitEvent<Schema>) {
  await submit(async () => {
    sentMessage.value = await auth.forgotPassword(event.data.email)
  })
}
</script>

<template>
  <UPageCard
    title="Forgot your password?"
    description="Enter your email and we'll send you a link to reset it."
  >
    <div
      v-if="sentMessage"
      class="flex flex-col gap-4"
    >
      <UAlert
        color="success"
        variant="subtle"
        icon="i-lucide-mail-check"
        :description="sentMessage"
      />
      <UButton
        to="/login"
        variant="ghost"
        color="neutral"
        icon="i-lucide-arrow-left"
        block
      >
        Back to sign in
      </UButton>
    </div>

    <UForm
      v-else
      :schema="schema"
      :state="state"
      class="flex flex-col gap-4"
      @submit="onSubmit"
    >
      <UFormField
        label="Email"
        name="email"
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          icon="i-lucide-mail"
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
        Send reset link
      </UButton>

      <ULink
        to="/login"
        class="text-center text-sm text-muted hover:text-default"
      >
        Back to sign in
      </ULink>
    </UForm>
  </UPageCard>
</template>
