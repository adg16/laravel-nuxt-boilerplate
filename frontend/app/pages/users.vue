<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'
import { PERMISSIONS } from '~/constants/permissions'
import type { User } from '~/types/user'

definePageMeta({
  breadcrumb: 'nav.users',
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

// Deactivating cuts off a user's access (and blocks their next sign-in) without
// deleting them; you can't do it to yourself or a protected account — matches
// the backend guard.
function activationTooltip(user: User): string {
  if (user.is_protected) return t('users.protectedTooltip')
  if (user.id === auth.user?.id) return t('users.cannotDeactivateSelf')
  return user.is_active ? t('users.deactivate') : t('users.activate')
}

const users = ref<User[]>([])
const roleNames = ref<string[]>([])
const total = ref(0)
const loading = ref(false)

// Server-side filter panel: free-text name/email, role multiselect (any of the
// picked roles), and a single status. Changing a filter refetches from page 1.
const filters = reactive({
  name: '',
  email: '',
  roles: [] as string[],
  status: [] as string[]
})

const STATUS_VALUES = ['active', 'inactive', 'verified', 'unverified'] as const
const statusOptions = computed(() =>
  STATUS_VALUES.map(value => ({ title: t(`users.status.${value}`), value }))
)

const hasActiveFilters = computed(() =>
  !!filters.name || !!filters.email || filters.roles.length > 0 || filters.status.length > 0
)

function clearFilters() {
  filters.name = ''
  filters.email = ''
  filters.roles = []
  filters.status = []
}

// Only name/email are server-sortable (the backend whitelists those columns);
// roles/status are composed values. The actions column is manage-only.
const headers = computed(() => [
  { title: t('table.name'), key: 'name' },
  { title: t('table.email'), key: 'email' },
  { title: t('table.roles'), key: 'roles', sortable: false },
  { title: t('table.status'), key: 'status', sortable: false },
  ...(canManage.value
    ? [{ title: t('table.actions'), key: 'actions', sortable: false, align: 'end' as const }]
    : [])
])

// The data table owns page / itemsPerPage / sortBy and emits them together via
// @update:options (which also fires once on mount — that's the initial load).
// `page` is bound so a filter change can reset it to the first page.
const page = ref(1)
const itemsPerPage = ref(25)
const itemsPerPageOptions = [10, 25, 50, 100]

interface DataTableOptions {
  page: number
  itemsPerPage: number
  sortBy: { key: string, order?: 'asc' | 'desc' }[]
}

let lastOptions: DataTableOptions | null = null
// Guards against out-of-order responses: only the most recent request may write
// results / clear loading, so a slow earlier fetch can't clobber a newer one.
let loadSeq = 0

async function onOptions(options: DataTableOptions) {
  lastOptions = options
  await load()
}

async function load() {
  if (!lastOptions) return
  const seq = ++loadSeq
  loading.value = true
  try {
    const sort = lastOptions.sortBy[0]
    const result = await usersApi.list({
      page: lastOptions.page,
      perPage: lastOptions.itemsPerPage,
      sortBy: sort?.key,
      sortDir: sort?.order,
      name: filters.name,
      email: filters.email,
      roles: filters.roles,
      status: filters.status
    })
    if (seq !== loadSeq) return
    users.value = result.data
    total.value = result.total
  } catch (e) {
    if (seq === loadSeq) notify(apiErrorMessage(e), 'error')
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

// Debounce filter changes (per-keystroke on the text fields) and reset to the
// first page. Resetting a non-1 page re-emits @update:options → load(); when
// already on page 1 there's no emit, so reload directly.
let filterTimer: ReturnType<typeof setTimeout> | undefined
watch(filters, () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    if (page.value !== 1) {
      page.value = 1
    } else {
      load()
    }
  }, 300)
}, { deep: true })

// Powers both the roles filter (needs only users.view) and the create/edit
// dialog (manage-only). Loaded for anyone who can see the page; a viewer lacking
// roles.view just gets an empty list (the request fails soft).
async function loadRoleOptions() {
  try {
    roleNames.value = await rolesApi.options()
  } catch {
    // Non-fatal: the filter/dialog just show no role options.
  }
}

onMounted(loadRoleOptions)

// --- Create (invite / set-password) / edit dialog ---
const dialog = ref(false)
const formRef = ref<VForm>()
const editing = ref<User | null>(null)
// Dialog fields render server (422) errors inline via :error-messages;
// hasFieldErrors gates the redundant bottom summary alert.
const { loading: saving, error, fieldErrors, hasFieldErrors, clearFieldError, submit } = useSubmit()
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
  clearFieldError()
  dialog.value = true
}

function openEdit(user: User) {
  editing.value = user
  state.name = user.name
  state.email = user.email
  state.roles = [...user.roles]
  error.value = ''
  clearFieldError()
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

// --- Activate / deactivate ---
// Reactivation is harmless, so it applies immediately; deactivation cuts off
// access, so it goes through a confirm dialog first.
const deactivateDialog = ref(false)
const deactivateTarget = ref<User | null>(null)
const deactivating = ref(false)

function toggleActivation(user: User) {
  if (user.is_active) {
    deactivateTarget.value = user
    deactivateDialog.value = true
  } else {
    activate(user)
  }
}

async function activate(user: User) {
  try {
    const { message } = await usersApi.activate(user.id)
    notify(message)
    await load()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

async function onDeactivate() {
  if (!deactivateTarget.value) return
  deactivating.value = true
  try {
    const { message } = await usersApi.deactivate(deactivateTarget.value.id)
    notify(message)
    deactivateDialog.value = false
    await load()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    deactivating.value = false
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

    <AppSearchPanel
      :active="hasActiveFilters"
      @clear="clearFilters"
    >
      <v-text-field
        v-model="filters.name"
        :label="$t('table.name')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        clearable
        hide-details
      />
      <v-text-field
        v-model="filters.email"
        :label="$t('table.email')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        clearable
        hide-details
      />
      <v-autocomplete
        v-model="filters.roles"
        :label="$t('table.roles')"
        :items="roleNames"
        density="comfortable"
        multiple
        chips
        closable-chips
        clearable
        hide-details
      />
      <v-autocomplete
        v-model="filters.status"
        :label="$t('table.status')"
        :items="statusOptions"
        density="comfortable"
        multiple
        chips
        closable-chips
        clearable
        hide-details
      />
    </AppSearchPanel>

    <v-card
      border
    >
      <v-data-table-server
        v-model:page="page"
        :headers="headers"
        :items="users"
        :items-length="total"
        :items-per-page="itemsPerPage"
        :items-per-page-options="itemsPerPageOptions"
        :loading="loading"
        :no-data-text="$t('common.noResults')"
        @update:options="onOptions"
      >
        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center ga-3">
            <AppUserAvatar
              :name="item.name"
              :src="item.avatar_url"
              :size="36"
            />
            <span class="font-weight-medium">{{ item.name }}</span>
          </div>
        </template>

        <template #[`item.email`]="{ item }">
          <span class="text-medium-emphasis">{{ item.email }}</span>
        </template>

        <template #[`item.roles`]="{ item }">
          <div
            v-if="item.roles.length"
            class="d-flex flex-wrap ga-1"
          >
            <v-chip
              v-for="role in item.roles"
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
        </template>

        <template #[`item.status`]="{ item }">
          <div class="d-flex flex-wrap ga-1">
            <v-chip
              :color="item.is_active ? 'success' : 'error'"
              :prepend-icon="item.is_active ? 'mdi-account-check-outline' : 'mdi-account-off-outline'"
              size="small"
              variant="tonal"
            >
              {{ item.is_active ? $t('users.status.active') : $t('users.deactivated') }}
            </v-chip>
            <v-chip
              :color="item.is_verified ? 'success' : 'warning'"
              :prepend-icon="item.is_verified ? 'mdi-check-circle-outline' : 'mdi-clock-outline'"
              size="small"
              variant="tonal"
            >
              {{ item.is_verified ? $t('users.verified') : $t('users.pending') }}
            </v-chip>
          </div>
        </template>

        <template
          v-if="canManage"
          #[`item.actions`]="{ item }"
        >
          <div class="text-no-wrap">
            <AppTableAction
              icon="mdi-email-sync-outline"
              :tooltip="resendTooltip(item)"
              :disabled="item.is_protected || item.is_verified"
              @click="resendInvite(item)"
            />
            <AppTableAction
              icon="mdi-pencil-outline"
              :tooltip="item.is_protected ? $t('users.protectedTooltip') : $t('common.edit')"
              :disabled="item.is_protected"
              @click="openEdit(item)"
            />
            <AppTableAction
              icon="mdi-shield-refresh-outline"
              :tooltip="resetTwoFactorTooltip(item)"
              :disabled="item.is_protected || !item.two_factor_enabled"
              @click="openResetTwoFactor(item)"
            />
            <AppTableAction
              :icon="item.is_active ? 'mdi-account-off-outline' : 'mdi-account-check-outline'"
              :tooltip="activationTooltip(item)"
              :disabled="item.is_protected || item.id === auth.user?.id"
              @click="toggleActivation(item)"
            />
            <AppTableAction
              icon="mdi-delete-outline"
              :tooltip="deleteTooltip(item)"
              :disabled="item.is_protected || item.id === auth.user?.id"
              @click="openDelete(item)"
            />
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Invite / edit dialog -->
    <AppFormDialog
      v-model="dialog"
      :title="editing ? $t('users.edit') : $t('users.new')"
      icon="mdi-account-outline"
      :saving="saving"
      :submit-label="editing ? $t('common.save') : (isSetPassword ? $t('users.create') : $t('users.sendInvite'))"
      @submit="onSubmit"
    >
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
          :error-messages="fieldErrors.name"
          @update:model-value="clearFieldError('name')"
        />
        <v-text-field
          v-model="state.email"
          type="email"
          :label="$t('fields.email')"
          :rules="emailRules"
          :error-messages="fieldErrors.email"
          @update:model-value="clearFieldError('email')"
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
            :error-messages="fieldErrors.password"
            @update:model-value="clearFieldError('password')"
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
          :error-messages="fieldErrors.roles"
          @update:model-value="clearFieldError('roles')"
        />

        <p
          v-if="!editing && !isSetPassword"
          class="text-body-small text-medium-emphasis mb-0"
        >
          {{ $t('users.inviteHint') }}
        </p>

        <v-alert
          v-if="error && !hasFieldErrors"
          type="error"
          variant="tonal"
          density="comfortable"
          :text="error"
        />
      </v-form>
    </AppFormDialog>

    <AppConfirmDialog
      v-model="deleteDialog"
      type="error"
      :title="$t('users.delete.title')"
      :text="$t('users.delete.text', { name: deleteTarget?.name })"
      :confirm-label="$t('common.delete')"
      :loading="deleting"
      @confirm="onDelete"
    />

    <AppConfirmDialog
      v-model="resetDialog"
      type="warning"
      :title="$t('users.resetTwoFactorConfirm.title')"
      :text="$t('users.resetTwoFactorConfirm.text', { name: resetTarget?.name })"
      :confirm-label="$t('users.resetTwoFactor')"
      :loading="resetting"
      @confirm="onResetTwoFactor"
    />

    <AppConfirmDialog
      v-model="deactivateDialog"
      type="warning"
      :title="$t('users.deactivateConfirm.title')"
      :text="$t('users.deactivateConfirm.text', { name: deactivateTarget?.name })"
      :confirm-label="$t('users.deactivate')"
      :loading="deactivating"
      @confirm="onDeactivate"
    />
  </div>
</template>
