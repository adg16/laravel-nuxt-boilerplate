<script setup lang="ts">
// Renders its default slot only when the current user is authorized. Pass a
// `permission` (checked via useAuthz's can, which honors the super-admin
// bypass), a `role`, or both — both must pass when both are given.
//
//   <Can permission="users.manage"><v-btn>New User</v-btn></Can>
const props = defineProps<{
  permission?: string
  role?: string
}>()

const { can, hasRole } = useAuthz()

const allowed = computed(() => {
  const permissionOk = props.permission ? can(props.permission) : true
  const roleOk = props.role ? hasRole(props.role) : true
  return permissionOk && roleOk
})
</script>

<template>
  <slot v-if="allowed" />
</template>
