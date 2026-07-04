<script setup lang="ts">
// Static placeholder data — swap for a real API-backed list later.
const users = [
  { name: 'Jane Cooper', email: 'jane@example.com', role: 'Admin', status: 'Active' },
  { name: 'Cody Fisher', email: 'cody@example.com', role: 'Editor', status: 'Active' },
  { name: 'Esther Howard', email: 'esther@example.com', role: 'Viewer', status: 'Pending' },
  { name: 'Cameron Williamson', email: 'cameron@example.com', role: 'Editor', status: 'Suspended' },
  { name: 'Brooklyn Simmons', email: 'brooklyn@example.com', role: 'Viewer', status: 'Active' },
  { name: 'Leslie Alexander', email: 'leslie@example.com', role: 'Admin', status: 'Active' },
  { name: 'Guy Hawkins', email: 'guy@example.com', role: 'Editor', status: 'Pending' }
]

const statusColor: Record<string, string> = {
  Active: 'success',
  Pending: 'warning',
  Suspended: 'error'
}
</script>

<template>
  <v-container
    fluid
    class="pa-4 pa-md-6"
  >
    <div class="d-flex flex-wrap align-center ga-4 mb-6">
      <div class="mr-auto">
        <h1 class="text-headline-small font-weight-bold">
          Users
        </h1>
        <p class="text-body-medium text-medium-emphasis">
          Manage the people who have access to this workspace.
        </p>
      </div>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
      >
        New User
      </v-btn>
    </div>

    <v-card
      border
      flat
    >
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              Name
            </th>
            <th class="text-left">
              Email
            </th>
            <th class="text-left">
              Role
            </th>
            <th class="text-left">
              Status
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.email"
          >
            <td>
              <div class="d-flex align-center ga-3">
                <v-avatar
                  color="primary"
                  size="36"
                >
                  <span class="text-label-medium font-weight-bold">{{ getInitials(user.name) }}</span>
                </v-avatar>
                <span class="font-weight-medium">{{ user.name }}</span>
              </div>
            </td>
            <td class="text-medium-emphasis">
              {{ user.email }}
            </td>
            <td>{{ user.role }}</td>
            <td>
              <v-chip
                :color="statusColor[user.status]"
                size="small"
                variant="tonal"
              >
                {{ user.status }}
              </v-chip>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </v-container>
</template>
