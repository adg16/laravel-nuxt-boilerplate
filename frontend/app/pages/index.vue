<script setup lang="ts">
definePageMeta({ breadcrumb: 'nav.dashboard' })

const stats = [
  { label: 'dashboard.stats.totalUsers', value: '1,204', icon: 'mdi-account-group-outline', color: 'primary' },
  { label: 'dashboard.stats.sessions', value: '318', icon: 'mdi-pulse', color: 'info' },
  { label: 'dashboard.stats.revenue', value: '$12.4k', icon: 'mdi-currency-usd', color: 'success' },
  { label: 'dashboard.stats.openTickets', value: '27', icon: 'mdi-ticket-outline', color: 'warning' }
]

const recent = [
  { name: 'Jane Cooper', email: 'jane@example.com', role: 'Admin', status: 'Active' },
  { name: 'Cody Fisher', email: 'cody@example.com', role: 'Editor', status: 'Active' },
  { name: 'Esther Howard', email: 'esther@example.com', role: 'Viewer', status: 'Pending' },
  { name: 'Cameron Williamson', email: 'cameron@example.com', role: 'Editor', status: 'Suspended' }
]

const statusColor: Record<string, string> = {
  Active: 'success',
  Pending: 'warning',
  Suspended: 'error'
}
</script>

<template>
  <div>
    <v-row>
      <v-col
        v-for="stat in stats"
        :key="stat.label"
        cols="12"
        sm="6"
        md="3"
      >
        <v-card
          border
          class="pa-4"
        >
          <div class="d-flex align-center ga-4">
            <v-avatar
              :color="stat.color"
              rounded="lg"
              size="48"
              variant="tonal"
            >
              <v-icon
                :icon="stat.icon"
                size="24"
              />
            </v-avatar>
            <div>
              <div class="text-headline-small font-weight-bold">
                {{ stat.value }}
              </div>
              <div class="text-body-small text-medium-emphasis">
                {{ $t(stat.label) }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card
      border
      class="mt-6"
    >
      <v-card-item>
        <v-card-title>
          {{ $t('dashboard.recentUsers') }}
        </v-card-title>
      </v-card-item>
      <v-divider />
      <v-table>
        <thead>
          <tr>
            <th class="text-left">
              {{ $t('table.name') }}
            </th>
            <th class="text-left">
              {{ $t('table.email') }}
            </th>
            <th class="text-left">
              {{ $t('table.role') }}
            </th>
            <th class="text-left">
              {{ $t('table.status') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in recent"
            :key="row.email"
          >
            <td class="font-weight-medium">
              {{ row.name }}
            </td>
            <td class="text-medium-emphasis">
              {{ row.email }}
            </td>
            <td>{{ row.role }}</td>
            <td>
              <v-chip
                :color="statusColor[row.status]"
                size="small"
                variant="tonal"
              >
                {{ $t(`status.${row.status.toLowerCase()}`) }}
              </v-chip>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </div>
</template>
