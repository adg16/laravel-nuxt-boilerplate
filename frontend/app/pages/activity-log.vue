<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Activity } from '~/types/activity'

definePageMeta({
  breadcrumb: 'nav.activityLog',
  permission: PERMISSIONS.ActivityView
})

const { t, locale } = useI18n()
const { notify } = useSnackbar()
const activityApi = useActivity()
const { eventColor, eventIcon, actionLabel, diffRows } = useActivityFormat()

const activities = ref<Activity[]>([])
const total = ref(0)
const loading = ref(false)

// Server-side filter panel: log-name + event multiselects and a content search
// (across actor name, subject name, and the change values). Values OR within
// each field and AND across fields (matching the backend). Changing a filter
// refetches from page 1.
const filters = reactive({
  logName: [] as string[],
  event: [] as string[],
  actor: '',
  search: '',
  dateFrom: null as string | null,
  dateTo: null as string | null
})

const logNameOptions = computed(() =>
  (['users', 'roles', 'settings'] as const).map(value => ({ title: t(`activityLog.logName.${value}`), value }))
)
const eventOptions = computed(() =>
  (['created', 'updated', 'deleted'] as const).map(value => ({ title: t(`activityLog.event.${value}`), value }))
)

const hasActiveFilters = computed(() =>
  filters.logName.length > 0 || filters.event.length > 0 || !!filters.actor
  || !!filters.search || !!filters.dateFrom || !!filters.dateTo
)

function clearFilters() {
  filters.logName = []
  filters.event = []
  filters.actor = ''
  filters.search = ''
  filters.dateFrom = null
  filters.dateTo = null
}

const fmtDate = (value?: string | null) => formatDateTime(value, locale.value)

// A one-line subject descriptor: "Users: Jane Doe". Settings have no model
// subject, so fall back to the changed setting's label from the diff.
function subjectText(item: Activity): string {
  const scope = t(`activityLog.logName.${item.log_name}`)
  const label = item.subject?.label ?? diffRows(item)[0]?.label ?? null
  return label ? `${scope}: ${label}` : scope
}

// Only the timestamp and log_name are server-sortable (backend whitelist); the
// rest are composed/nested values.
const headers = computed(() => [
  { title: t('activityLog.when'), key: 'created_at' },
  { title: t('activityLog.actor'), key: 'actor', sortable: false },
  { title: t('activityLog.action'), key: 'action', sortable: false },
  { title: t('activityLog.subject'), key: 'subject', sortable: false },
  { title: '', key: 'data-table-expand', align: 'center' as const }
])

const page = ref(1)
const itemsPerPage = ref(25)
const itemsPerPageOptions = [10, 25, 50, 100]
// The expand model holds the (string) row keys of open rows. Use an explicit
// string key so the model and item-value agree (avoids number/string mismatches).
const expanded = ref<string[]>([])
const rowKey = (item: Activity) => String(item.id)

// Expand-all / collapse-all toggle for the whole page.
const allExpanded = computed(() =>
  activities.value.length > 0 && expanded.value.length === activities.value.length
)
function toggleExpandAll() {
  expanded.value = allExpanded.value ? [] : activities.value.map(rowKey)
}

interface DataTableOptions {
  page: number
  itemsPerPage: number
  sortBy: { key: string, order?: 'asc' | 'desc' }[]
}

let lastOptions: DataTableOptions | null = null
// Guards against out-of-order responses: only the newest request may write.
let loadSeq = 0

async function onOptions(options: DataTableOptions) {
  lastOptions = options
  await load()
}

async function load() {
  if (!lastOptions) return
  const seq = ++loadSeq
  loading.value = true
  try {
    const sort = lastOptions.sortBy[0]
    const result = await activityApi.list({
      page: lastOptions.page,
      perPage: lastOptions.itemsPerPage,
      sortBy: sort?.key,
      sortDir: sort?.order,
      logName: filters.logName,
      event: filters.event,
      actor: filters.actor,
      search: filters.search,
      dateFrom: filters.dateFrom,
      dateTo: filters.dateTo
    })
    if (seq !== loadSeq) return
    activities.value = result.data
    total.value = result.total
    // Collapse everything on a fresh page/filter load (stale keys wouldn't match).
    expanded.value = []
  } catch (e) {
    if (seq === loadSeq) notify(apiErrorMessage(e), 'error')
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

// Debounce filter changes and reset to the first page (see users.vue for why).
let filterTimer: ReturnType<typeof setTimeout> | undefined
watch(filters, () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    if (page.value !== 1) {
      page.value = 1
    } else {
      load()
    }
  }, 300)
}, { deep: true })
</script>

<template>
  <div>
    <AppPageHeader :description="$t('activityLog.description_text')" />

    <AppSearchPanel
      :active="hasActiveFilters"
      @clear="clearFilters"
    >
      <v-autocomplete
        v-model="filters.logName"
        :label="$t('activityLog.filters.logName')"
        :items="logNameOptions"
        density="comfortable"
        multiple
        chips
        closable-chips
        clearable
        hide-details
      />
      <v-autocomplete
        v-model="filters.event"
        :label="$t('activityLog.filters.event')"
        :items="eventOptions"
        density="comfortable"
        multiple
        chips
        closable-chips
        clearable
        hide-details
      />
      <v-text-field
        v-model="filters.actor"
        :label="$t('activityLog.filters.actor')"
        :placeholder="$t('activityLog.filters.actorHint')"
        prepend-inner-icon="mdi-account-outline"
        density="comfortable"
        clearable
        hide-details
      />
      <v-text-field
        v-model="filters.search"
        :label="$t('activityLog.filters.search')"
        :placeholder="$t('activityLog.filters.searchHint')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        clearable
        hide-details
      />
      <AppDateRangeField
        v-model:from="filters.dateFrom"
        v-model:to="filters.dateTo"
        :label="$t('activityLog.filters.dateRange')"
      />
    </AppSearchPanel>

    <v-card border>
      <v-data-table-server
        v-model:page="page"
        v-model:expanded="expanded"
        :headers="headers"
        :items="activities"
        :items-length="total"
        :items-per-page="itemsPerPage"
        :items-per-page-options="itemsPerPageOptions"
        :loading="loading"
        :no-data-text="$t('common.noResults')"
        :item-value="rowKey"
        show-expand
        @update:options="onOptions"
      >
        <!-- Expand/collapse-all control in the expand column header, aligned over
             the per-row chevrons. -->
        <template #[`header.data-table-expand`]>
          <AppTableAction
            v-if="activities.length"
            :icon="allExpanded ? 'mdi-unfold-less-horizontal' : 'mdi-unfold-more-horizontal'"
            :tooltip="allExpanded ? $t('common.collapseAll') : $t('common.expandAll')"
            @click="toggleExpandAll"
          />
        </template>

        <template #[`item.created_at`]="{ item }">
          <span class="text-no-wrap">{{ fmtDate(item.created_at) }}</span>
        </template>

        <template #[`item.actor`]="{ item }">
          <div
            v-if="item.causer"
            class="d-flex align-center ga-3"
          >
            <AppUserAvatar
              :name="item.causer.name"
              :size="32"
            />
            <span class="font-weight-medium">{{ item.causer.name }}</span>
          </div>
          <span
            v-else
            class="text-medium-emphasis"
          >{{ $t('activityLog.system') }}</span>
        </template>

        <template #[`item.action`]="{ item }">
          <v-chip
            :color="eventColor(item)"
            :prepend-icon="eventIcon(item)"
            size="small"
            variant="tonal"
          >
            {{ actionLabel(item) }}
          </v-chip>
        </template>

        <template #[`item.subject`]="{ item }">
          <span>{{ subjectText(item) }}</span>
        </template>

        <template #expanded-row="{ columns, item }">
          <tr class="activity-expanded">
            <td
              :colspan="columns.length"
              class="pa-0"
            >
              <div class="activity-expanded__panel px-4 py-3">
                <div class="d-flex align-center ga-2 mb-3">
                  <v-icon
                    icon="mdi-swap-horizontal"
                    size="small"
                    class="text-medium-emphasis"
                  />
                  <span class="text-label-small text-uppercase text-medium-emphasis">
                    {{ $t('activityLog.changes') }}
                  </span>
                </div>
                <AppActivityDiff :activity="item" />
              </div>
            </td>
          </tr>
        </template>
      </v-data-table-server>
    </v-card>
  </div>
</template>

<style scoped>
/* Distinct inset panel for the expanded diff: a faint surface tint with a brand
   accent rail, so the change detail reads as a nested block rather than a bare
   full-width row. Works in both themes via the theme tokens. */
.activity-expanded__panel {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-left: 3px solid rgb(var(--v-theme-primary));
}
</style>
