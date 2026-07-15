<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { User } from '~/types/user'

definePageMeta({
  breadcrumb: [{ title: 'nav.users', to: '/users' }, { title: 'activityLog.history' }],
  permission: PERMISSIONS.ActivityView
})

const route = useRoute()
const { notify } = useSnackbar()
const usersApi = useUsers()

const user = ref<User | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    user.value = await usersApi.get(Number(route.params.id))
  } catch (e) {
    // Unknown id, or a restricted account the viewer can't see — back to the list.
    notify(apiErrorMessage(e), 'error')
    await navigateTo('/users')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <AppPageHeader>
      <template
        v-if="user"
        #description
      >
        <i18n-t
          keypath="activityLog.historyForUser"
          tag="span"
        >
          <template #name>
            <span class="font-weight-bold text-high-emphasis">{{ user.name }}</span>
          </template>
        </i18n-t>
      </template>
    </AppPageHeader>
    <v-skeleton-loader
      v-if="loading"
      type="article"
    />
    <AppActivityHistory
      v-else-if="user"
      subject-type="user"
      :subject-id="user.id"
      :page-size="15"
    />
  </div>
</template>
