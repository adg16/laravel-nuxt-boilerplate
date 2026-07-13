<script setup lang="ts">
// The create/edit role form, shared by the /roles/new and /roles/[id] pages.
// With no `role` it creates; with one it edits that record. Loads the permission
// catalog itself and delegates the permission UI to <RolePermissionsField>.
import { z } from 'zod'
import type { VForm } from 'vuetify/components'
import type { Permission, Role } from '~/types/rbac'

const props = defineProps<{
  role?: Role | null
}>()

const { t } = useI18n()
const { notify } = useSnackbar()
const rolesApi = useRoles()
const permissionsApi = usePermissions()

const editing = computed(() => !!props.role)

const permissions = ref<Permission[]>([])
const loadingPermissions = ref(true)

const state = reactive({
  name: props.role?.name ?? '',
  permissions: [...(props.role?.permissions ?? [])]
})

const formRef = ref<VForm>()
// The name field renders server (422) errors inline via :error-messages;
// hasFieldErrors gates the redundant summary alert (same pattern as the users form).
const { loading: saving, error, fieldErrors, hasFieldErrors, clearFieldError, submit } = useSubmit()
const nameRules = [zodRule(z.string().min(1, t('validation.required')))]

async function loadPermissions() {
  loadingPermissions.value = true
  try {
    permissions.value = await permissionsApi.list()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    loadingPermissions.value = false
  }
}
onMounted(loadPermissions)

function cancel() {
  navigateTo('/roles')
}

async function onSubmit() {
  const { valid } = await formRef.value!.validate()
  if (!valid) return

  await submit(async () => {
    const payload = { name: state.name, permissions: state.permissions }
    if (props.role) {
      await rolesApi.update(props.role.id, payload)
      notify(t('roles.updated'))
    } else {
      await rolesApi.create(payload)
      notify(t('roles.created'))
    }
    await navigateTo('/roles')
  })
}
</script>

<template>
  <v-form
    ref="formRef"
    validate-on="submit"
    @submit.prevent="onSubmit"
  >
    <v-card border>
      <v-card-text class="pa-6">
        <v-row class="mt-4">
          <v-col
            cols="12"
            md="8"
            lg="6"
          >
            <v-text-field
              v-model="state.name"
              :label="$t('fields.roleName')"
              :rules="nameRules"
              :error-messages="fieldErrors.name"
              autofocus
              @update:model-value="clearFieldError('name')"
            />
          </v-col>
        </v-row>

        <div class="text-title-medium font-weight-bold mt-8">
          {{ $t('roles.permissions') }}
        </div>

        <v-skeleton-loader
          v-if="loadingPermissions"
          type="list-item-two-line@3"
        />
        <RolePermissionsField
          v-else
          v-model="state.permissions"
          :permissions="permissions"
        />

        <v-alert
          v-if="error && !hasFieldErrors"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mt-4"
          :text="error"
        />
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4">
        <v-spacer />
        <v-btn
          variant="text"
          :disabled="saving"
          @click="cancel"
        >
          {{ $t('common.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          type="submit"
          :loading="saving"
        >
          {{ editing ? $t('roles.saveChanges') : $t('roles.createRole') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-form>
</template>
