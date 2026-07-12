<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'
import { PERMISSIONS } from '~/constants/permissions'
import type { Permission, Role } from '~/types/rbac'

definePageMeta({
  breadcrumb: 'nav.roles',
  permission: PERMISSIONS.RolesView
})

const { t } = useI18n()
const { notify } = useSnackbar()
const { can } = useAuthz()
const { actionLabel, resourceLabel, fullLabel } = usePermissionLabels()
const rolesApi = useRoles()
const permissionsApi = usePermissions()

const SUPER_ADMIN = 'super-admin'
const canManage = computed(() => can(PERMISSIONS.RolesManage))

const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])
const total = ref(0)
const loading = ref(false)

// Group the flat permission list by the segment before the dot ("users.view"
// → group "users") so the editor reads as sections rather than a long list.
const permissionGroups = computed(() => {
  const groups: Record<string, Permission[]> = {}
  for (const permission of permissions.value) {
    const group = permission.name.split('.')[0] ?? permission.name
    ;(groups[group] ??= []).push(permission)
  }
  return Object.entries(groups).map(([name, items]) => ({ name, items }))
})

// Server-side filter panel: free-text name and a permission multiselect (roles
// granting any of the picked permissions).
const filters = reactive({
  name: '',
  permissions: [] as string[]
})

const permissionOptions = computed(() =>
  permissions.value.map(permission => ({ title: fullLabel(permission.name), value: permission.name }))
)

const hasActiveFilters = computed(() =>
  !!filters.name || filters.permissions.length > 0
)

function clearFilters() {
  filters.name = ''
  filters.permissions = []
}

// Name and user count are server-sortable; the permission list is a composed value.
const headers = computed(() => [
  { title: t('table.role'), key: 'name' },
  { title: t('table.permissions'), key: 'permissions', sortable: false },
  { title: t('table.users'), key: 'users_count' },
  ...(canManage.value
    ? [{ title: t('table.actions'), key: 'actions', sortable: false, align: 'end' as const }]
    : [])
])

// The data table owns page / itemsPerPage / sortBy and emits them together via
// @update:options (which also fires once on mount — that's the initial load).
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
    const result = await rolesApi.list({
      page: lastOptions.page,
      perPage: lastOptions.itemsPerPage,
      sortBy: sort?.key,
      sortDir: sort?.order,
      name: filters.name,
      permissions: filters.permissions
    })
    if (seq !== loadSeq) return
    roles.value = result.data
    total.value = result.total
  } catch (e) {
    if (seq === loadSeq) notify(apiErrorMessage(e), 'error')
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

// Debounce filter changes (per-keystroke on the name field) and reset to page 1.
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

// Permissions power both the filter dropdown and the create/edit dialog.
async function loadPermissions() {
  try {
    permissions.value = await permissionsApi.list()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

onMounted(loadPermissions)

// --- Create / edit dialog ---
const dialog = ref(false)
const formRef = ref<VForm>()
const editing = ref<Role | null>(null)
const { loading: saving, error, submit } = useSubmit()
const state = reactive({ name: '', permissions: [] as string[] })

const nameRules = [zodRule(z.string().min(1, t('validation.required')))]

function openCreate() {
  editing.value = null
  state.name = ''
  state.permissions = []
  error.value = ''
  dialog.value = true
}

function openEdit(role: Role) {
  editing.value = role
  state.name = role.name
  state.permissions = [...role.permissions]
  error.value = ''
  dialog.value = true
}

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    const payload = { name: state.name, permissions: state.permissions }
    if (editing.value) {
      await rolesApi.update(editing.value.id, payload)
      notify(t('roles.updated'))
    } else {
      await rolesApi.create(payload)
      notify(t('roles.created'))
    }
    dialog.value = false
    await load()
  })
}

// --- Delete ---
const deleteDialog = ref(false)
const deleteTarget = ref<Role | null>(null)
const deleting = ref(false)

function openDelete(role: Role) {
  deleteTarget.value = role
  deleteDialog.value = true
}

async function onDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    const { message } = await rolesApi.remove(deleteTarget.value.id)
    notify(message)
    deleteDialog.value = false
    await load()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div>
    <AppPageHeader>
      <Can :permission="PERMISSIONS.RolesManage">
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ $t('roles.new') }}
        </v-btn>
      </Can>
    </AppPageHeader>

    <AppSearchPanel
      :active="hasActiveFilters"
      @clear="clearFilters"
    >
      <v-text-field
        v-model="filters.name"
        :label="$t('fields.roleName')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        clearable
        hide-details
      />
      <v-autocomplete
        v-model="filters.permissions"
        :label="$t('table.permissions')"
        :items="permissionOptions"
        density="comfortable"
        multiple
        chips
        closable-chips
        clearable
        hide-details
      />
    </AppSearchPanel>

    <v-card border>
      <v-data-table-server
        v-model:page="page"
        :headers="headers"
        :items="roles"
        :items-length="total"
        :items-per-page="itemsPerPage"
        :items-per-page-options="itemsPerPageOptions"
        :loading="loading"
        :no-data-text="$t('common.noResults')"
        @update:options="onOptions"
      >
        <template #[`item.name`]="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            :color="item.name === SUPER_ADMIN ? 'primary' : undefined"
          >
            {{ item.name }}
          </v-chip>
        </template>

        <template #[`item.permissions`]="{ item }">
          <span
            v-if="item.name === SUPER_ADMIN"
            class="text-medium-emphasis"
          >{{ $t('roles.allAccess') }}</span>
          <span
            v-else-if="!item.permissions.length"
            class="text-medium-emphasis"
          >—</span>
          <div
            v-else
            class="d-flex flex-wrap ga-1"
          >
            <v-chip
              v-for="permission in item.permissions"
              :key="permission"
              size="x-small"
              variant="outlined"
            >
              {{ fullLabel(permission) }}
            </v-chip>
          </div>
        </template>

        <template #[`item.users_count`]="{ item }">
          {{ item.users_count ?? 0 }}
        </template>

        <template
          v-if="canManage"
          #[`item.actions`]="{ item }"
        >
          <div class="text-no-wrap">
            <AppTableAction
              icon="mdi-pencil-outline"
              :tooltip="item.name === SUPER_ADMIN ? $t('roles.protectedTooltip') : $t('common.edit')"
              :disabled="item.name === SUPER_ADMIN"
              @click="openEdit(item)"
            />
            <AppTableAction
              icon="mdi-delete-outline"
              :tooltip="item.name === SUPER_ADMIN ? $t('roles.protectedTooltip') : $t('common.delete')"
              :disabled="item.name === SUPER_ADMIN"
              @click="openDelete(item)"
            />
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Create / edit dialog -->
    <AppFormDialog
      v-model="dialog"
      :title="editing ? $t('roles.edit') : $t('roles.new')"
      icon="mdi-shield-account-outline"
      :max-width="640"
      :saving="saving"
      @submit="onSubmit"
    >
      <v-form
        ref="formRef"
        validate-on="submit"
        @submit.prevent="onSubmit"
      >
        <v-text-field
          v-model="state.name"
          :label="$t('fields.roleName')"
          :rules="nameRules"
        />

        <div class="text-title-small mt-4 mb-2">
          {{ $t('roles.permissions') }}
        </div>
        <div
          v-for="group in permissionGroups"
          :key="group.name"
          class="mb-3"
        >
          <div class="text-label-large text-medium-emphasis mb-1">
            {{ resourceLabel(group.name) }}
          </div>
          <div class="d-flex flex-wrap ga-2">
            <v-checkbox
              v-for="permission in group.items"
              :key="permission.id"
              v-model="state.permissions"
              :value="permission.name"
              :label="actionLabel(permission.name)"
              density="compact"
              hide-details
            />
          </div>
        </div>

        <v-alert
          v-if="error"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mt-2"
          :text="error"
        />
      </v-form>
    </AppFormDialog>

    <AppConfirmDialog
      v-model="deleteDialog"
      type="error"
      :title="$t('roles.delete.title')"
      :text="$t('roles.delete.text', { name: deleteTarget?.name })"
      :confirm-label="$t('common.delete')"
      :loading="deleting"
      @confirm="onDelete"
    />
  </div>
</template>
