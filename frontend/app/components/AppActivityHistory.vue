<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Activity } from '~/types/activity'

// A reusable per-record history panel: the audit trail for one subject
// (a user or a role), with an event-type filter + content search, rendered as a
// compact, paged timeline. Only fetches/renders for users who can view the log.
const props = withDefaults(defineProps<{
  subjectType: 'user' | 'role'
  subjectId: number
  // Entries per page.
  pageSize?: number
}>(), { pageSize: 10 })

const { t, locale } = useI18n()
const { can } = useAuthz()
const activityApi = useActivity()
const { eventColor, eventIcon, actionLabel } = useActivityFormat()

const canView = can(PERMISSIONS.ActivityView)

const items = ref<Activity[]>([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const error = ref(false)

// Event-type multiselect, a content search (across actor name and the change
// values), and a date range — so what you filter/search matches what the
// timeline shows.
const filters = reactive({
  event: [] as string[],
  actor: '',
  search: '',
  dateFrom: null as string | null,
  dateTo: null as string | null
})

const eventOptions = computed(() =>
  (['created', 'updated', 'deleted'] as const).map(value => ({ title: t(`activityLog.event.${value}`), value }))
)

const hasActiveFilters = computed(() =>
  filters.event.length > 0 || !!filters.actor || !!filters.search
  || !!filters.dateFrom || !!filters.dateTo
)

function clearFilters() {
  filters.event = []
  filters.actor = ''
  filters.search = ''
  filters.dateFrom = null
  filters.dateTo = null
}

const pageCount = computed(() => Math.max(1, Math.ceil(total.value / props.pageSize)))

const fmtDate = (value?: string | null) => formatDateTime(value, locale.value)

// Guards against out-of-order responses: only the newest request may write.
let loadSeq = 0

async function load() {
  // Guard here too (not just in the template) so we never fire the request.
  if (!canView) return
  const seq = ++loadSeq
  loading.value = true
  error.value = false
  try {
    const result = await activityApi.list({
      page: page.value,
      perPage: props.pageSize,
      subjectType: props.subjectType,
      subjectId: props.subjectId,
      event: filters.event,
      actor: filters.actor,
      search: filters.search,
      dateFrom: filters.dateFrom,
      dateTo: filters.dateTo,
      sortDir: 'desc'
    })
    if (seq !== loadSeq) return
    items.value = result.data
    total.value = result.total
  } catch {
    if (seq === loadSeq) error.value = true
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

// Reset to the first page and refetch when pointed at a different record.
watch(() => [props.subjectType, props.subjectId], () => {
  page.value = 1
  load()
}, { immediate: true })

// Debounce filter changes and reset to the first page (see the list pages for
// why this resets `page` rather than calling load twice).
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

// Page control changes just refetch the requested page.
watch(page, load)
</script>

<template>
  <Can :permission="PERMISSIONS.ActivityView">
    <div>
      <AppSearchPanel
        :active="hasActiveFilters"
        @clear="clearFilters"
      >
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
          :placeholder="$t('activityLog.filters.searchValueHint')"
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
        <v-card-text>
          <v-skeleton-loader
            v-if="loading && !items.length"
            type="list-item-three-line"
          />

          <p
            v-else-if="error"
            class="text-body-medium text-medium-emphasis mb-0"
          >
            {{ $t('common.genericError') }}
          </p>

          <p
            v-else-if="!items.length"
            class="text-body-medium text-medium-emphasis mb-0"
          >
            {{ hasActiveFilters ? $t('common.noResults') : $t('activityLog.empty') }}
          </p>

          <template v-else>
            <v-timeline
              side="end"
              density="compact"
              align="start"
              truncate-line="both"
            >
              <v-timeline-item
                v-for="item in items"
                :key="item.id"
                :dot-color="eventColor(item)"
                :icon="eventIcon(item)"
                size="small"
              >
                <div class="d-flex flex-column ga-1">
                  <div class="d-flex flex-wrap align-center ga-2">
                    <span class="font-weight-medium">{{ actionLabel(item) }}</span>
                    <span class="text-body-small text-medium-emphasis">
                      {{ item.causer?.name ?? $t('activityLog.system') }} · {{ fmtDate(item.created_at) }}
                    </span>
                  </div>
                  <AppActivityDiff :activity="item" />
                </div>
              </v-timeline-item>
            </v-timeline>

            <div
              v-if="pageCount > 1"
              class="d-flex justify-center pt-2"
            >
              <v-pagination
                v-model="page"
                :length="pageCount"
                :total-visible="5"
                :disabled="loading"
                density="comfortable"
                rounded="circle"
              />
            </div>
          </template>
        </v-card-text>
      </v-card>
    </div>
  </Can>
</template>
