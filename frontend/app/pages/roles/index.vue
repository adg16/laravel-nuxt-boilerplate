<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Permission, Role } from '~/types/rbac'

definePageMeta({
  breadcrumb: 'nav.roles',
  permission: PERMISSIONS.RolesView
})

const { t } = useI18n()
const { notify } = useSnackbar()
const { can } = useAuthz()
const { fullLabel, resourceLabel, groupPermissionNames } = usePermissionLabels()
const rolesApi = useRoles()
const permissionsApi = usePermissions()

const SUPER_ADMIN = 'Super Admin'
const canManage = computed(() => can(PERMISSIONS.RolesManage))

const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])
const total = ref(0)
const loading = ref(false)

// Server-side filter panel: free-text name and a permission multiselect (roles
// granting any of the picked permissions).
const filters = reactive({
  name: '',
  permissions: [] as string[]
})

// Group the multiselect by resource with a subheader per group (Vuetify renders
// `type: 'subheader'` items as non-selectable group headings), so the filter
// reads the same way as the role editor's grouped permission picker. Item titles
// stay the full "Action Resource" label so selected chips and type-ahead search
// remain unambiguous once the subheaders scroll away.
type PermissionOption
  = | { type: 'subheader', title: string }
    | { title: string, value: string }

const permissionOptions = computed<PermissionOption[]>(() => {
  const groups: Record<string, Permission[]> = {}
  for (const permission of permissions.value) {
    const key = permission.name.split('.')[0] ?? permission.name
    ;(groups[key] ??= []).push(permission)
  }
  const options: PermissionOption[] = []
  for (const [key, items] of Object.entries(groups).sort((a, b) => resourceLabel(a[0]).localeCompare(resourceLabel(b[0])))) {
    options.push({ type: 'subheader', title: resourceLabel(key) })
    for (const permission of items) {
      options.push({ title: fullLabel(permission.name), value: permission.name })
    }
  }
  return options
})

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

// Permissions power the filter dropdown.
async function loadPermissions() {
  try {
    permissions.value = await permissionsApi.list()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  }
}

onMounted(loadPermissions)

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
          to="/roles/new"
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
            class="perm-groups py-1"
          >
            <div
              v-for="group in groupPermissionNames(item.permissions)"
              :key="group.key"
              class="perm-group d-flex align-center ga-2"
            >
              <span class="perm-group__label text-body-small font-weight-medium text-medium-emphasis">
                {{ group.label }}
              </span>
              <div class="d-flex flex-wrap align-center ga-1">
                <v-chip
                  v-for="permission in group.items"
                  :key="permission"
                  size="x-small"
                  variant="tonal"
                >
                  {{ fullLabel(permission) }}
                </v-chip>
              </div>
            </div>
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
              @click="navigateTo(`/roles/${item.id}`)"
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

<style scoped>
/* One resource per row: a fixed-width label column keeps every group's action
   chips aligned in a second column, so the cell reads as a small table. */
.perm-groups {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.perm-group__label {
  flex: 0 0 auto;
  min-width: 5rem;
}
</style>
