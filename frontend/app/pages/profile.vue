<script setup lang="ts">
// Self-service account page for the signed-in user: edit their own profile
// (name, email) and change their password. Both write through Fortify's headless
// endpoints via the auth store (see stores/auth.ts). Admin account management
// lives on the permissioned /users page instead.
import { z } from 'zod'
import type { VForm } from 'vuetify/components'

definePageMeta({
  breadcrumb: 'nav.profile',
  subtitle: 'profile.subtitle'
})

const auth = useAuthStore()
const { notify } = useSnackbar()
const { t } = useI18n()

// --- Avatar ---
const AVATAR_MAX_MB = 2
const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const avatarInput = ref<HTMLInputElement>()
const avatarBusy = ref(false)

function pickAvatar() {
  avatarInput.value?.click()
}

async function onAvatarSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // reset so re-picking the same file still fires change
  if (!file) return

  // Validate client-side for instant feedback; the API enforces the same rules.
  if (!AVATAR_TYPES.includes(file.type)) {
    notify(t('profile.avatar.invalidType'), 'error')
    return
  }
  if (file.size > AVATAR_MAX_MB * 1024 * 1024) {
    notify(t('profile.avatar.tooLarge', { max: AVATAR_MAX_MB }), 'error')
    return
  }

  avatarBusy.value = true
  try {
    await auth.uploadAvatar(file)
    notify(t('profile.avatar.updated'))
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    avatarBusy.value = false
  }
}

async function removeAvatar() {
  avatarBusy.value = true
  try {
    await auth.removeAvatar()
    notify(t('profile.avatar.removed'))
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    avatarBusy.value = false
  }
}

// --- Profile information ---
const profileForm = ref<VForm>()
const profile = reactive({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? ''
})
const { loading: savingProfile, error: profileError, submit: submitProfile } = useSubmit()

const nameRules = [zodRule(z.string().min(1, t('validation.required')))]
const emailRules = [zodRule(z.string().email(t('validation.email')))]

// Re-seed the form only when the *identity* changes (hydration / account switch),
// not on every store reassignment — otherwise saving the profile or uploading an
// avatar (both replace `auth.user`) would clobber unsaved edits in these fields.
watch(() => auth.user?.id, () => {
  if (auth.user) {
    profile.name = auth.user.name
    profile.email = auth.user.email
  }
})

const profileDirty = computed(() =>
  profile.name !== (auth.user?.name ?? '') || profile.email !== (auth.user?.email ?? '')
)

async function onSaveProfile() {
  const { valid } = await profileForm.value!.validate()
  if (!valid) return

  await submitProfile(async () => {
    await auth.updateProfile({ name: profile.name, email: profile.email })
    notify(t('profile.info.saved'))
  })
}

// --- Change password ---
const passwordForm = ref<VForm>()
const password = reactive({
  current_password: '',
  password: '',
  password_confirmation: ''
})
const { loading: savingPassword, error: passwordError, submit: submitPassword } = useSubmit()

const currentPasswordRules = [zodRule(z.string().min(1, t('validation.passwordRequired')))]
const newPasswordRules = [zodRule(z.string().min(8, t('validation.passwordMin')))]
const confirmationRules = [
  (v: string) => v === password.password || t('validation.passwordsNoMatch')
]

async function onChangePassword() {
  const { valid } = await passwordForm.value!.validate()
  if (!valid) return

  await submitPassword(async () => {
    const message = await auth.updatePassword({ ...password })
    notify(message)
    passwordForm.value!.reset()
  })
}
</script>

<template>
  <div>
    <AppPageHeader :description="$t('profile.description')" />

    <div class="d-flex flex-column ga-6">
      <!-- Avatar -->
      <v-card
        border
        flat
      >
        <v-card-item>
          <v-card-title class="text-title-large">
            {{ $t('profile.avatar.title') }}
          </v-card-title>
          <v-card-subtitle>{{ $t('profile.avatar.subtitle') }}</v-card-subtitle>
        </v-card-item>

        <v-divider />

        <v-card-text>
          <div class="d-flex align-center ga-6 flex-wrap">
            <AppUserAvatar
              :name="auth.user?.name"
              :src="auth.user?.avatar_url"
              :size="96"
            />
            <div class="d-flex flex-column ga-3">
              <div class="d-flex flex-wrap ga-3">
                <v-btn
                  color="primary"
                  variant="flat"
                  prepend-icon="mdi-camera-outline"
                  :loading="avatarBusy"
                  @click="pickAvatar"
                >
                  {{ auth.user?.avatar_url ? $t('profile.avatar.change') : $t('profile.avatar.upload') }}
                </v-btn>
                <v-btn
                  v-if="auth.user?.avatar_url"
                  color="error"
                  variant="outlined"
                  prepend-icon="mdi-delete-outline"
                  :disabled="avatarBusy"
                  @click="removeAvatar"
                >
                  {{ $t('profile.avatar.remove') }}
                </v-btn>
              </div>
              <p class="text-body-small text-medium-emphasis mb-0">
                {{ $t('profile.avatar.hint', { max: AVATAR_MAX_MB }) }}
              </p>
            </div>
          </div>

          <input
            ref="avatarInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="d-none"
            @change="onAvatarSelected"
          >
        </v-card-text>
      </v-card>

      <!-- Profile information -->
      <v-card
        border
        flat
      >
        <v-card-item>
          <v-card-title class="text-title-large">
            {{ $t('profile.info.title') }}
          </v-card-title>
          <v-card-subtitle>{{ $t('profile.info.subtitle') }}</v-card-subtitle>
        </v-card-item>

        <v-divider />

        <v-card-text>
          <v-form
            ref="profileForm"
            validate-on="submit"
            class="d-flex flex-column ga-4"
            style="max-width: 480px"
            @submit.prevent="onSaveProfile"
          >
            <v-text-field
              v-model="profile.name"
              :label="$t('fields.name')"
              autocomplete="name"
              prepend-inner-icon="mdi-account-outline"
              :rules="nameRules"
            />

            <v-text-field
              v-model="profile.email"
              type="email"
              :label="$t('fields.email')"
              autocomplete="email"
              prepend-inner-icon="mdi-email-outline"
              :rules="emailRules"
            />

            <v-alert
              v-if="profileError"
              type="error"
              variant="tonal"
              density="comfortable"
              :text="profileError"
            />

            <div>
              <v-btn
                type="submit"
                color="primary"
                variant="flat"
                :loading="savingProfile"
                :disabled="!profileDirty"
              >
                {{ $t('common.save') }}
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>

      <!-- Change password -->
      <v-card
        border
        flat
      >
        <v-card-item>
          <v-card-title class="text-title-large">
            {{ $t('profile.password.title') }}
          </v-card-title>
          <v-card-subtitle>{{ $t('profile.password.subtitle') }}</v-card-subtitle>
        </v-card-item>

        <v-divider />

        <v-card-text>
          <v-form
            ref="passwordForm"
            validate-on="submit"
            class="d-flex flex-column ga-4"
            style="max-width: 480px"
            @submit.prevent="onChangePassword"
          >
            <PasswordInput
              v-model="password.current_password"
              :label="$t('fields.currentPassword')"
              autocomplete="current-password"
              placeholder="••••••••"
              prepend-inner-icon="mdi-lock-outline"
              :rules="currentPasswordRules"
            />

            <PasswordInput
              v-model="password.password"
              :label="$t('fields.newPassword')"
              autocomplete="new-password"
              placeholder="••••••••"
              prepend-inner-icon="mdi-lock-outline"
              :rules="newPasswordRules"
            />

            <PasswordInput
              v-model="password.password_confirmation"
              :label="$t('fields.confirmPassword')"
              autocomplete="new-password"
              placeholder="••••••••"
              prepend-inner-icon="mdi-lock-outline"
              :rules="confirmationRules"
            />

            <v-alert
              v-if="passwordError"
              type="error"
              variant="tonal"
              density="comfortable"
              :text="passwordError"
            />

            <div>
              <v-btn
                type="submit"
                color="primary"
                variant="flat"
                :loading="savingPassword"
              >
                {{ $t('profile.password.submit') }}
              </v-btn>
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </div>
  </div>
</template>
