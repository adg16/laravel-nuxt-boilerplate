<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Role } from '~/types/rbac'

definePageMeta({
  breadcrumb: [{ title: 'nav.roles', to: '/roles' }, { title: 'activityLog.history' }],
  permission: PERMISSIONS.ActivityView
})

const route = useRoute()
const { notify } = useSnackbar()
const rolesApi = useRoles()

const role = ref<Role | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    role.value = await rolesApi.get(Number(route.params.id))
  } catch (e) {
    // Unknown id, or the super-admin role a non-super-admin can't see — go back.
    notify(apiErrorMessage(e), 'error')
    await navigateTo('/roles')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <AppPageHeader>
      <template
        v-if="role"
        #description
      >
        <i18n-t
          keypath="activityLog.historyForRole"
          tag="span"
        >
          <template #name>
            <span class="font-weight-bold text-high-emphasis">{{ role.name }}</span>
          </template>
        </i18n-t>
      </template>
    </AppPageHeader>
    <v-skeleton-loader
      v-if="loading"
      type="article"
    />
    <AppActivityHistory
      v-else-if="role"
      subject-type="role"
      :subject-id="role.id"
      :page-size="15"
    />
  </div>
</template>
