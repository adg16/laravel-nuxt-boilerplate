<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'
import { PERMISSIONS } from '~/constants/permissions'
import type { Role } from '~/types/rbac'
import type { User } from '~/types/user'

definePageMeta({
  breadcrumb: 'nav.users',
  subtitle: 'users.subtitle',
  permission: PERMISSIONS.UsersView
})

const { t } = useI18n()
const { notify } = useSnackbar()
const { can } = useAuthz()
const auth = useAuthStore()
const config = useConfigStore()
const usersApi = useUsers()
const rolesApi = useRoles()

const canManage = computed(() => can(PERMISSIONS.UsersManage))

// Protected accounts (super-admin / System) can't be edited or deleted — the
// API enforces it and reports it via `is_protected`.
function deleteTooltip(user: User): string {
  if (user.is_protected) return t('users.protectedTooltip')
  if (user.id === auth.user?.id) return t('users.cannotDeleteSelf')
  return t('common.delete')
}

// Resending only makes sense while the invite is still pending (and never for
// protected accounts) — matches the backend guard.
function resendTooltip(user: User): string {
  if (user.is_protected) return t('users.protectedTooltip')
  if (user.is_verified) return t('users.alreadyAccepted')
  return t('users.resendInvite')
}

// Resetting 2FA is the lockout recovery for a user who lost their authenticator;
// only meaningful when they actually have it enabled, and never for protected
// accounts — matches the backend guard.
function resetTwoFactorTooltip(user: User): string {
  if (user.is_protected) return t('users.protectedTooltip')
  if (!user.two_factor_enabled) return t('users.noTwoFactor')
  return t('users.resetTwoFactor')
}

const users = ref<User[]>([])
const roleNames = ref<string[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    users.value = await usersApi.list()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

async function loadRoleOptions() {
  if (!canManage.value) return
  try {
    roleNames.value = (await rolesApi.list()).map((role: Role) => role.name)
  } catch {
    // Non-fatal: the dialog just shows no role options.
  }
}

onMounted(() => {
  load()
  loadRoleOptions()
})

// --- Create (invite / set-password) / edit dialog ---
const dialog = ref(false)
const formRef = ref<VForm>()
const editing = ref<User | null>(null)
const { loading: saving, error, submit } = useSubmit()
const state = reactive({
  name: '',
  email: '',
  roles: [] as string[],
  method: 'invite' as 'invite' | 'set_password',
  password: '',
  password_confirmation: ''
})

const nameRules = [zodRule(z.string().min(1, t('validation.required')))]
const emailRules = [zodRule(z.string().email(t('validation.email')))]
const passwordRules = [zodRule(z.string().min(8, t('validation.passwordMin')))]
const confirmationRules = [
  (v: string) => v === state.password || t('validation.passwordsNoMatch')
]

// Only offer the invite/set-password choice when the app is configured for it;
// otherwise the mode is fixed and the form just follows it.
const canChooseMethod = computed(() => config.userCreationMode === 'choice')
const effectiveMethod = computed(() =>
  config.userCreationMode === 'choice' ? state.method : config.userCreationMode
)
const isSetPassword = computed(() => effectiveMethod.value === 'set_password')

function openCreate() {
  editing.value = null
  state.name = ''
  state.email = ''
  state.roles = []
  state.method = 'invite'
  state.password = ''
  state.password_confirmation = ''
  error.value = ''
  dialog.value = true
}

function openEdit(user: User) {
  editing.value = user
  state.name = user.name
  state.email = user.email
  state.roles = [...user.roles]
  error.value = ''
  dialog.value = true
}

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    if (editing.value) {
      await usersApi.update(editing.value.id, { name: state.name, email: state.email, roles: state.roles })
      notify(t('users.updated'))
    } else {
      await usersApi.create({
        name: state.name,
        email: state.email,
        roles: state.roles,
        ...(canChooseMethod.value ? { method: effectiveMethod.value } : {}),
        ...(isSetPassword.value
          ? { password: state.password, password_confirmation: state.password_confirmation }
          : {})
      })
      notify(isSetPassword.value ? t('users.created') : t('users.invited'))
    }
    dialog.value = false
    await load()
  })
}

async function resendInvite(user: User) {
  try {
    const { message } = await usersApi.resendInvite(user.id)
    notify(message)
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

// --- Delete ---
const deleteDialog = ref(false)
const deleteTarget = ref<User | null>(null)
const deleting = ref(false)

function openDelete(user: User) {
  deleteTarget.value = user
  deleteDialog.value = true
}

async function onDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    const { message } = await usersApi.remove(deleteTarget.value.id)
    notify(message)
    deleteDialog.value = false
    await load()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    deleting.value = false
  }
}

// --- Reset two-factor ---
const resetDialog = ref(false)
const resetTarget = ref<User | null>(null)
const resetting = ref(false)

function openResetTwoFactor(user: User) {
  resetTarget.value = user
  resetDialog.value = true
}

async function onResetTwoFactor() {
  if (!resetTarget.value) return
  resetting.value = true
  try {
    const { message } = await usersApi.resetTwoFactor(resetTarget.value.id)
    notify(message)
    resetDialog.value = false
    await load()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    resetting.value = false
  }
}
</script>

<template>
  <div>
    <AppPageHeader>
      <Can :permission="PERMISSIONS.UsersManage">
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ $t('users.new') }}
        </v-btn>
      </Can>
    </AppPageHeader>

    <v-card
      border
      flat
    >
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              {{ $t('table.name') }}
            </th>
            <th class="text-left">
              {{ $t('table.email') }}
            </th>
            <th class="text-left">
              {{ $t('table.roles') }}
            </th>
            <th class="text-left">
              {{ $t('table.status') }}
            </th>
            <th
              v-if="canManage"
              class="text-right"
            >
              {{ $t('table.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td
              :colspan="canManage ? 5 : 4"
              class="text-center text-medium-emphasis py-8"
            >
              <v-progress-circular
                indeterminate
                size="24"
              />
            </td>
          </tr>
          <tr
            v-for="user in users"
            v-else
            :key="user.id"
          >
            <td>
              <div class="d-flex align-center ga-3">
                <AppUserAvatar
                  :name="user.name"
                  :src="user.avatar_url"
                  :size="36"
                />
                <span class="font-weight-medium">{{ user.name }}</span>
              </div>
            </td>
            <td class="text-medium-emphasis">
              {{ user.email }}
            </td>
            <td class="py-2">
              <div
                v-if="user.roles.length"
                class="d-flex flex-wrap ga-1"
              >
                <v-chip
                  v-for="role in user.roles"
                  :key="role"
                  size="small"
                  variant="tonal"
                >
                  {{ role }}
                </v-chip>
              </div>
              <span
                v-else
                class="text-medium-emphasis"
              >—</span>
            </td>
            <td>
              <v-chip
                :color="user.is_verified ? 'success' : 'warning'"
                :prepend-icon="user.is_verified ? 'mdi-check-circle-outline' : 'mdi-clock-outline'"
                size="small"
                variant="tonal"
              >
                {{ user.is_verified ? $t('users.verified') : $t('users.pending') }}
              </v-chip>
            </td>
            <td
              v-if="canManage"
              class="text-right text-no-wrap"
            >
              <AppTableAction
                icon="mdi-email-sync-outline"
                :tooltip="resendTooltip(user)"
                :disabled="user.is_protected || user.is_verified"
                @click="resendInvite(user)"
              />
              <AppTableAction
                icon="mdi-pencil-outline"
                :tooltip="user.is_protected ? $t('users.protectedTooltip') : $t('common.edit')"
                :disabled="user.is_protected"
                @click="openEdit(user)"
              />
              <AppTableAction
                icon="mdi-shield-refresh-outline"
                :tooltip="resetTwoFactorTooltip(user)"
                :disabled="user.is_protected || !user.two_factor_enabled"
                @click="openResetTwoFactor(user)"
              />
              <AppTableAction
                icon="mdi-delete-outline"
                :tooltip="deleteTooltip(user)"
                :disabled="user.is_protected || user.id === auth.user?.id"
                @click="openDelete(user)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Invite / edit dialog -->
    <v-dialog
      v-model="dialog"
      max-width="520"
      :persistent="saving"
    >
      <v-card>
        <v-card-title class="text-title-large">
          {{ editing ? $t('users.edit') : $t('users.new') }}
        </v-card-title>
        <v-card-text>
          <v-form
            ref="formRef"
            validate-on="submit"
            class="d-flex flex-column ga-4"
            @submit.prevent="onSubmit"
          >
            <v-text-field
              v-model="state.name"
              :label="$t('fields.name')"
              :rules="nameRules"
            />
            <v-text-field
              v-model="state.email"
              type="email"
              :label="$t('fields.email')"
              :rules="emailRules"
            />

            <!-- Access method (create only). The toggle shows only when the app
                 lets the admin choose; otherwise the fixed mode drives the form. -->
            <template v-if="!editing">
              <div v-if="canChooseMethod">
                <div class="text-label-large text-medium-emphasis mb-1">
                  {{ $t('users.methodLabel') }}
                </div>
                <v-btn-toggle
                  v-model="state.method"
                  color="primary"
                  variant="outlined"
                  density="comfortable"
                  divided
                  mandatory
                >
                  <v-btn
                    value="invite"
                    prepend-icon="mdi-email-outline"
                  >
                    {{ $t('users.method.invite') }}
                  </v-btn>
                  <v-btn
                    value="set_password"
                    prepend-icon="mdi-lock-outline"
                  >
                    {{ $t('users.method.password') }}
                  </v-btn>
                </v-btn-toggle>
              </div>

              <PasswordInput
                v-if="isSetPassword"
                v-model="state.password"
                :label="$t('fields.password')"
                autocomplete="new-password"
                placeholder="••••••••"
                prepend-inner-icon="mdi-lock-outline"
                :rules="passwordRules"
              />
              <PasswordInput
                v-if="isSetPassword"
                v-model="state.password_confirmation"
                :label="$t('fields.confirmPassword')"
                autocomplete="new-password"
                placeholder="••••••••"
                prepend-inner-icon="mdi-lock-outline"
                :rules="confirmationRules"
              />
            </template>

            <v-select
              v-model="state.roles"
              :items="roleNames"
              :label="$t('fields.roles')"
              multiple
              chips
              closable-chips
            />

            <p
              v-if="!editing && !isSetPassword"
              class="text-body-small text-medium-emphasis mb-0"
            >
              {{ $t('users.inviteHint') }}
            </p>

            <v-alert
              v-if="error"
              type="error"
              variant="tonal"
              density="comfortable"
              :text="error"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn
            variant="text"
            :disabled="saving"
            @click="dialog = false"
          >
            {{ $t('common.cancel') }}
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            @click="onSubmit"
          >
            {{ editing ? $t('common.save') : (isSetPassword ? $t('users.create') : $t('users.sendInvite')) }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <AppConfirmDialog
      v-model="deleteDialog"
      :title="$t('users.delete.title')"
      :text="$t('users.delete.text', { name: deleteTarget?.name })"
      :confirm-label="$t('common.delete')"
      :loading="deleting"
      @confirm="onDelete"
    />

    <AppConfirmDialog
      v-model="resetDialog"
      :title="$t('users.resetTwoFactorConfirm.title')"
      :text="$t('users.resetTwoFactorConfirm.text', { name: resetTarget?.name })"
      :confirm-label="$t('users.resetTwoFactor')"
      :loading="resetting"
      @confirm="onResetTwoFactor"
    />
  </div>
</template>
