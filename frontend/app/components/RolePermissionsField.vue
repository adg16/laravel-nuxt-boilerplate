<script setup lang="ts">
// The role editor's permission picker: a `v-model` bound to the selected
// permission-name array. It groups the flat permission list by resource
// ("users.view" → "users"), renders each group as a collapsible panel with a
// per-group select-all (indeterminate when partially selected) and a
// selected/total count, plus an expand/collapse-all toggle — so the form stays
// navigable as the permission catalog grows.
import type { Permission } from '~/types/rbac'

const model = defineModel<string[]>({ required: true })

const props = defineProps<{
  permissions: Permission[]
}>()

const { resourceLabel, actionLabel, fullLabel } = usePermissionLabels()

// Deterministic ordering so the editor reads the same way regardless of the
// order the API returns permissions in: groups sorted by resource label, and
// within a group actions follow the shared `actionRank` priority (view before
// manage) with an alphabetical fallback for any new action.
interface Group {
  key: string
  label: string
  items: Permission[]
}

const groups = computed<Group[]>(() => {
  const map: Record<string, Permission[]> = {}
  for (const permission of props.permissions) {
    const key = permission.name.split('.')[0] ?? permission.name
    ;(map[key] ??= []).push(permission)
  }
  return Object.entries(map)
    .map(([key, items]) => ({
      key,
      label: resourceLabel(key),
      items: [...items].sort((a, b) =>
        actionRank(a.name) - actionRank(b.name) || a.name.localeCompare(b.name))
    }))
    .sort((a, b) => a.label.localeCompare(b.label))
})

// Free-text filter over the permission catalog — useful once the list grows past
// a screenful. A group whose resource name matches surfaces all its permissions;
// otherwise only the permissions whose action label or key match are shown, and
// groups with no match drop out entirely.
const query = ref('')

const filteredGroups = computed<Group[]>(() => {
  // `clearable` sets the field to null on clear, so coerce before trimming.
  const q = (query.value ?? '').trim().toLowerCase()
  if (!q) return groups.value
  return groups.value
    .map((group) => {
      if (group.label.toLowerCase().includes(q)) return group
      const items = group.items.filter(item =>
        actionLabel(item.name).toLowerCase().includes(q) || item.name.toLowerCase().includes(q))
      return { ...group, items }
    })
    .filter(group => group.items.length > 0)
})

// Which panels are expanded (by group key). Default: open the groups that
// already grant something — so an existing role opens on its active sections —
// or every group when nothing is selected yet, so a brand-new role shows all
// its options. Only seeded once, so user toggles afterwards aren't overridden.
const open = ref<string[]>([])
const initialised = ref(false)
watch(groups, (list) => {
  if (initialised.value || list.length === 0) return
  const active = list.filter(group => group.items.some(item => model.value.includes(item.name)))
  open.value = (active.length ? active : list).map(group => group.key)
  initialised.value = true
}, { immediate: true })

// While filtering, force every surviving group open so matches are always
// visible without an extra click.
watch(query, (q) => {
  if (q?.trim()) open.value = filteredGroups.value.map(group => group.key)
})

const allExpanded = computed(() =>
  filteredGroups.value.length > 0 && filteredGroups.value.every(group => open.value.includes(group.key))
)

function toggleAll() {
  open.value = allExpanded.value ? [] : filteredGroups.value.map(group => group.key)
}

function selectedCount(group: Group): number {
  return group.items.filter(item => model.value.includes(item.name)).length
}

// null = indeterminate (some but not all selected), drives the header checkbox.
function groupState(group: Group): boolean | null {
  const count = selectedCount(group)
  if (count === 0) return false
  if (count === group.items.length) return true
  return null
}

// Checking (from empty or indeterminate) selects the whole group; unchecking
// clears it — without disturbing selections in other groups.
function toggleGroup(group: Group, value: boolean | null) {
  const names = group.items.map(item => item.name)
  const others = model.value.filter(name => !names.includes(name))
  model.value = value ? [...others, ...names] : others
}
</script>

<template>
  <div>
    <div class="d-flex align-center ga-2 mb-2">
      <span class="text-body-medium text-medium-emphasis">{{ $t('roles.permissionsHint') }}</span>
      <v-spacer />
      <v-btn
        variant="text"
        size="small"
        :prepend-icon="allExpanded ? 'mdi-unfold-less-horizontal' : 'mdi-unfold-more-horizontal'"
        @click="toggleAll"
      >
        {{ allExpanded ? $t('roles.collapseAll') : $t('roles.expandAll') }}
      </v-btn>
    </div>

    <v-text-field
      v-model="query"
      :label="$t('roles.searchPermissions')"
      prepend-inner-icon="mdi-magnify"
      density="comfortable"
      clearable
      hide-details
      class="mb-3"
    />

    <v-alert
      v-if="filteredGroups.length === 0"
      type="info"
      variant="tonal"
      density="comfortable"
      :text="$t('roles.noPermissionsMatch')"
    />

    <v-expansion-panels
      v-else
      v-model="open"
      multiple
      variant="accordion"
    >
      <v-expansion-panel
        v-for="group in filteredGroups"
        :key="group.key"
        :value="group.key"
      >
        <v-expansion-panel-title>
          <div class="d-flex align-center ga-3 flex-grow-1">
            <v-checkbox
              :model-value="groupState(group) === true"
              :indeterminate="groupState(group) === null"
              :aria-label="$t('roles.selectAllIn', { group: group.label })"
              density="compact"
              hide-details
              @click.stop
              @update:model-value="toggleGroup(group, $event)"
            />
            <span class="text-title-medium font-weight-bold">{{ group.label }}</span>
            <v-spacer />
            <v-chip
              size="small"
              variant="tonal"
              :color="selectedCount(group) ? 'primary' : undefined"
            >
              {{ selectedCount(group) }}/{{ group.items.length }}
            </v-chip>
          </div>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="role-permissions-field__grid ps-9">
            <v-checkbox
              v-for="permission in group.items"
              :key="permission.id"
              v-model="model"
              :value="permission.name"
              :label="fullLabel(permission.name)"
              density="compact"
              hide-details
            />
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
/* Give each panel header a subtly tinted background so it reads as a distinct
   band from the plain-surface content below it. The on-surface overlay adapts
   to both light and dark themes (a faint grey either way). */
.v-expansion-panels :deep(.v-expansion-panel-title) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.v-expansion-panels :deep(.v-expansion-panel-title--active) {
  background-color: rgba(var(--v-theme-on-surface), 0.06);
}

/* Actions flow into as many columns as fit (each ~12rem wide) so a group with
   many permissions stays a compact grid rather than a tall single column. */
.role-permissions-field__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: 0 1rem;
}
</style>
