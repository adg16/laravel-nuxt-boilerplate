<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Permission } from '~/types/rbac'

definePageMeta({
  breadcrumb: 'nav.permissions',
  subtitle: 'permissions.subtitle',
  permission: PERMISSIONS.PermissionsView
})

const { t } = useI18n()
const { notify } = useSnackbar()
const { list } = usePermissions()
const { fullLabel } = usePermissionLabels()

const permissions = ref<Permission[]>([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    permissions.value = await list()
  } catch {
    notify(t('common.genericError'), 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <AppPageHeader :description="$t('permissions.readonlyHint')" />

    <v-card
      border
      flat
    >
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              {{ $t('table.permission') }}
            </th>
            <th class="text-left">
              {{ $t('table.usedBy') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td
              colspan="2"
              class="text-center text-medium-emphasis py-8"
            >
              <v-progress-circular
                indeterminate
                size="24"
              />
            </td>
          </tr>
          <tr
            v-for="permission in permissions"
            v-else
            :key="permission.id"
          >
            <td>
              <div class="d-flex align-center ga-3">
                <v-icon
                  icon="mdi-lock-outline"
                  size="16"
                  class="text-medium-emphasis"
                  :aria-label="$t('permissions.systemLocked')"
                />
                <div>
                  <div class="font-weight-medium">
                    {{ fullLabel(permission.name) }}
                  </div>
                  <code class="text-body-small text-medium-emphasis">{{ permission.name }}</code>
                </div>
              </div>
            </td>
            <td>
              <div
                v-if="permission.roles.length"
                class="d-flex flex-wrap ga-1 py-2"
              >
                <v-chip
                  v-for="role in permission.roles"
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
              >{{ $t('permissions.unused') }}</span>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </div>
</template>
