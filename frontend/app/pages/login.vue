<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true

  try {
    await auth.login({ email: email.value, password: password.value })
    router.push('/')
  } catch {
    error.value = 'Invalid credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <UPageCard title="Sign in">
    <form
      class="flex flex-col gap-4"
      @submit.prevent="handleSubmit"
    >
      <UFormField label="Email">
        <UInput
          v-model="email"
          type="email"
          name="email"
          autocomplete="email"
          class="w-full"
          required
        />
      </UFormField>

      <UFormField label="Password">
        <UInput
          v-model="password"
          type="password"
          name="password"
          autocomplete="current-password"
          class="w-full"
          required
        />
      </UFormField>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :title="error"
      />

      <UButton
        type="submit"
        block
        :loading="loading"
      >
        Sign in
      </UButton>
    </form>
  </UPageCard>
</template>
