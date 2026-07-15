<script setup lang="ts">
// A date-range filter field: a read-only text field showing the selected range
// that opens a Vuetify range date picker in a menu. Binds two models — `from`
// and `to` — as yyyy-mm-dd strings (or null when cleared).
const from = defineModel<string | null>('from', { default: null })
const to = defineModel<string | null>('to', { default: null })

defineProps<{ label?: string }>()

const { t, locale } = useI18n()
const menu = ref(false)

// Local yyyy-mm-dd (avoids the UTC shift `toISOString()` would introduce).
function toIsoDate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Parse a yyyy-mm-dd string as a local date (not UTC midnight).
function parseIsoDate(s: string): Date {
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1)
}

// Every day between two dates (inclusive) — the shape Vuetify's range picker
// expects for its value.
function datesBetween(start: Date, end: Date): Date[] {
  const out: Date[] = []
  const cur = new Date(start)
  while (cur <= end) {
    out.push(new Date(cur))
    cur.setDate(cur.getDate() + 1)
  }
  return out
}

// Bridge the from/to strings to the picker's Date[] range value.
const pickerDates = computed<Date[]>({
  get() {
    if (!from.value || !to.value) return []
    return datesBetween(parseIsoDate(from.value), parseIsoDate(to.value))
  },
  set(dates) {
    const list = dates ?? []
    if (!list.length) {
      from.value = null
      to.value = null
      return
    }
    const sorted = [...list].sort((a, b) => a.getTime() - b.getTime())
    const first = sorted[0]
    const last = sorted[sorted.length - 1]
    from.value = first ? toIsoDate(first) : null
    to.value = last ? toIsoDate(last) : null
  }
})

const displayText = computed(() => {
  if (!from.value || !to.value) return ''
  const fmt = (s: string) => parseIsoDate(s).toLocaleDateString(locale.value, { dateStyle: 'medium' })
  return from.value === to.value ? fmt(from.value) : `${fmt(from.value)} – ${fmt(to.value)}`
})

function clear() {
  from.value = null
  to.value = null
}
</script>

<template>
  <v-menu
    v-model="menu"
    :close-on-content-click="false"
  >
    <template #activator="{ props: activatorProps }">
      <v-text-field
        v-bind="activatorProps"
        :model-value="displayText"
        :label="label"
        prepend-inner-icon="mdi-calendar-range"
        density="comfortable"
        readonly
        clearable
        hide-details
        @click:clear="clear"
      />
    </template>
    <v-date-picker
      v-model="pickerDates"
      multiple="range"
      show-adjacent-months
    >
      <template #actions>
        <v-btn
          variant="text"
          @click="menu = false"
        >
          {{ t('common.done') }}
        </v-btn>
      </template>
    </v-date-picker>
  </v-menu>
</template>
