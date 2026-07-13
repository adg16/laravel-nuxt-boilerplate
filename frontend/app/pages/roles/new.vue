<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'

definePageMeta({
  breadcrumb: [{ title: 'nav.roles', to: '/roles' }, { title: 'roles.new' }],
  permission: PERMISSIONS.RolesManage
})
</script>

<template>
  <div>
    <AppPageHeader :description="$t('roles.newDescription')" />
    <RoleEditor />
  </div>
</template>
