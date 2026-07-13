<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Role } from '~/types/rbac'

definePageMeta({
  breadcrumb: [{ title: 'nav.roles', to: '/roles' }, { title: 'roles.edit' }],
  permission: PERMISSIONS.RolesManage
})

const route = useRoute()
const { t } = useI18n()
const { notify } = useSnackbar()
const rolesApi = useRoles()

const role = ref<Role | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const data = await rolesApi.get(Number(route.params.id))
    // The super-admin role is the Gate::before bypass — protected from edits for
    // everyone (the backend also 422s). Bounce direct navigation back to the list.
    if (data.name === 'Super Admin') {
      notify(t('roles.protectedTooltip'), 'error')
      await navigateTo('/roles')
      return
    }
    role.value = data
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
    await navigateTo('/roles')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <AppPageHeader :description="role ? $t('roles.editDescription', { name: role.name }) : undefined" />
    <v-skeleton-loader
      v-if="loading"
      type="article"
    />
    <RoleEditor
      v-else-if="role"
      :role="role"
    />
  </div>
</template>
