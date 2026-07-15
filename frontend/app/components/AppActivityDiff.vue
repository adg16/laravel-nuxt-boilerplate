<script setup lang="ts">
import type { Activity } from '~/types/activity'

// Renders one activity's field changes as a before → after diff: each field is a
// labelled row with the removed value (struck-through, error tint), an arrow, and
// the added value (success tint). Shows only the relevant side for create/delete
// events. Shared by the activity-log page (expanded row) and the history panel.
const props = defineProps<{ activity: Activity }>()

const { diffRows } = useActivityFormat()
const rows = computed(() => diffRows(props.activity))
</script>

<template>
  <div>
    <div
      v-if="rows.length"
      class="d-flex flex-column ga-2"
    >
      <div
        v-for="row in rows"
        :key="row.field"
        class="activity-diff__row d-flex flex-wrap align-baseline ga-2 text-body-medium"
      >
        <span class="activity-diff__label text-label-small text-uppercase text-medium-emphasis">
          {{ row.label }}
        </span>
        <div class="activity-diff__values d-flex flex-wrap align-center ga-2">
          <span
            v-if="row.hasOld"
            class="text-medium-emphasis text-decoration-line-through"
          >{{ row.old }}</span>
          <v-icon
            v-if="row.hasOld && row.hasNew"
            icon="mdi-arrow-right"
            size="small"
            class="text-medium-emphasis"
          />
          <span v-if="row.hasNew">{{ row.new }}</span>
          <span
            v-if="!row.hasOld && !row.hasNew"
            class="text-medium-emphasis"
          >—</span>
        </div>
      </div>
    </div>
    <span
      v-else
      class="text-medium-emphasis text-body-medium"
    >{{ $t('activityLog.noDetails') }}</span>
  </div>
</template>

<style scoped>
/* Fixed-width field label keeps the value chips aligned in a second column so
   multiple changes read as a small table. */
.activity-diff__label {
  flex: 0 0 auto;
  min-width: 6rem;
}
</style>
