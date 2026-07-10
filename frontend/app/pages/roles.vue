<script setup lang="ts">
import { z } from 'zod'
import type { VForm } from 'vuetify/components'
import { PERMISSIONS } from '~/constants/permissions'
import type { Permission, Role } from '~/types/rbac'

definePageMeta({
  breadcrumb: 'nav.roles',
  subtitle: 'roles.subtitle',
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
const loading = ref(true)

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

async function load() {
  loading.value = true
  try {
    const [rolesData, permissionsData] = await Promise.all([rolesApi.list(), permissionsApi.list()])
    roles.value = rolesData
    permissions.value = permissionsData
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)

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

    <v-card
      border
      flat
    >
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              {{ $t('table.role') }}
            </th>
            <th class="text-left">
              {{ $t('table.permissions') }}
            </th>
            <th class="text-left">
              {{ $t('table.users') }}
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
              :colspan="canManage ? 4 : 3"
              class="text-center text-medium-emphasis py-8"
            >
              <v-progress-circular
                indeterminate
                size="24"
              />
            </td>
          </tr>
          <tr
            v-for="role in roles"
            v-else
            :key="role.id"
          >
            <td>
              <v-chip
                size="small"
                variant="tonal"
                :color="role.name === SUPER_ADMIN ? 'primary' : undefined"
              >
                {{ role.name }}
              </v-chip>
            </td>
            <td class="py-2">
              <span
                v-if="role.name === SUPER_ADMIN"
                class="text-medium-emphasis"
              >{{ $t('roles.allAccess') }}</span>
              <span
                v-else-if="!role.permissions.length"
                class="text-medium-emphasis"
              >—</span>
              <div
                v-else
                class="d-flex flex-wrap ga-1"
              >
                <v-chip
                  v-for="permission in role.permissions"
                  :key="permission"
                  size="x-small"
                  variant="outlined"
                >
                  {{ fullLabel(permission) }}
                </v-chip>
              </div>
            </td>
            <td>{{ role.users_count ?? 0 }}</td>
            <td
              v-if="canManage"
              class="text-right text-no-wrap"
            >
              <AppTableAction
                icon="mdi-pencil-outline"
                :tooltip="role.name === SUPER_ADMIN ? $t('roles.protectedTooltip') : $t('common.edit')"
                :disabled="role.name === SUPER_ADMIN"
                @click="openEdit(role)"
              />
              <AppTableAction
                icon="mdi-delete-outline"
                :tooltip="role.name === SUPER_ADMIN ? $t('roles.protectedTooltip') : $t('common.delete')"
                :disabled="role.name === SUPER_ADMIN"
                @click="openDelete(role)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Create / edit dialog -->
    <v-dialog
      v-model="dialog"
      max-width="640"
      :persistent="saving"
    >
      <v-card>
        <v-card-title class="text-title-large">
          {{ editing ? $t('roles.edit') : $t('roles.new') }}
        </v-card-title>
        <v-card-text>
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
            {{ $t('common.save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <AppConfirmDialog
      v-model="deleteDialog"
      :title="$t('roles.delete.title')"
      :text="$t('roles.delete.text', { name: deleteTarget?.name })"
      :confirm-label="$t('common.delete')"
      :loading="deleting"
      @confirm="onDelete"
    />
  </div>
</template>
