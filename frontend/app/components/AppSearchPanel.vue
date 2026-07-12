<script setup lang="ts">
// Reusable search/filter panel layout: a collapsible bordered card with a
// responsive grid of filter fields and an optional "Clear" action shown when
// filters are active. The page supplies the actual fields via the default slot
// (so each stays strongly typed) and owns the filter state + clearing logic.
//
//   <AppSearchPanel :active="hasActiveFilters" @clear="clearFilters">
//     <v-text-field v-model="filters.name" :label="$t('table.name')" … />
//     <v-select v-model="filters.roles" :items="roleNames" multiple … />
//   </AppSearchPanel>
defineProps<{
  // Whether any filter currently has a value — toggles the Clear button.
  active?: boolean
}>()

defineEmits<{ clear: [] }>()

const expanded = ref(true)
</script>

<template>
  <v-card
    flat
    class="mb-4"
  >
    <v-card-text class="d-flex flex-column ga-3">
      <div
        class="app-search-panel__header d-flex align-center ga-2 mx-n4 px-4 pb-3 text-medium-emphasis"
        role="button"
        tabindex="0"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
        @keydown.enter.prevent="expanded = !expanded"
        @keydown.space.prevent="expanded = !expanded"
      >
        <v-icon
          icon="mdi-filter-variant"
          size="20"
        />
        <span class="text-label-large">{{ $t('common.filters') }}</span>
        <v-spacer />
        <v-btn
          v-if="active"
          color="primary"
          variant="text"
          size="small"
          prepend-icon="mdi-close"
          @click.stop="$emit('clear')"
        >
          {{ $t('common.clearFilters') }}
        </v-btn>
        <v-icon
          :icon="expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'"
          size="20"
        />
      </div>
      <v-expand-transition>
        <div
          v-show="expanded"
          class="app-search-panel__grid"
        >
          <slot />
        </div>
      </v-expand-transition>
    </v-card-text>
  </v-card>
</template>

<style scoped>
/* Primary accent line under the header, matching the dialog title motif. Spans
   the full card width (the mx-n4/px-4 offsets the v-card-text padding). */
.app-search-panel__header {
  cursor: pointer;
  border-bottom: 1px solid rgb(var(--v-theme-primary));
}

/* Fields flow into as many columns as fit, each at least ~14rem, so the panel
   stays one tidy row on wide screens and stacks on narrow ones. */
.app-search-panel__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
}
</style>
