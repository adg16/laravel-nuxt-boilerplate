<script setup lang="ts">
definePageMeta({
  breadcrumb: 'Roles',
  subtitle: 'Define what each group of users is allowed to do.'
})

// Static placeholder data — swap for a real API-backed list later.
const roles = [
  { name: 'Admin', description: 'Full access to every setting and resource.', users: 3, color: 'primary' },
  { name: 'Editor', description: 'Can create and edit content, but not manage users.', users: 12, color: 'info' },
  { name: 'Viewer', description: 'Read-only access to content and reports.', users: 48, color: 'success' },
  { name: 'Billing', description: 'Manages subscriptions, invoices, and payment methods.', users: 2, color: 'warning' }
]
</script>

<template>
  <div>
    <AppPageHeader>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
      >
        New Role
      </v-btn>
    </AppPageHeader>

    <v-card
      border
      flat
    >
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              Role
            </th>
            <th class="text-left">
              Description
            </th>
            <th class="text-left">
              Users
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="role in roles"
            :key="role.name"
          >
            <td>
              <v-chip
                :color="role.color"
                size="small"
                variant="tonal"
              >
                {{ role.name }}
              </v-chip>
            </td>
            <td class="text-medium-emphasis">
              {{ role.description }}
            </td>
            <td>{{ role.users }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </div>
</template>
