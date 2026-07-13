// Turns code-defined permission identifiers ("users.manage") into friendly,
// localized labels for display. Permission names are `resource.action`:
//   actionLabel("users.manage")   → "Manage"
//   resourceLabel("users")        → "Users"
//   fullLabel("users.manage")     → "Manage Users"
// Known resources/actions are localized (permissions.resources / .actions);
// anything else falls back to a humanized version so new permissions still
// read reasonably without a translation.
function humanize(value: string): string {
  return value.replace(/[._-]/g, ' ').replace(/\b\w/g, char => char.toUpperCase()).trim()
}

// Fixed action priority (view before manage) so grouped permissions always read
// the same way regardless of source order; unknown actions sort alphabetically
// last. Exported so both the roles list (groupPermissionNames) and the editor's
// picker order permissions from one definition.
const ACTION_ORDER = ['view', 'manage']
export function actionRank(name: string): number {
  const action = name.split('.').slice(1).join('.')
  const index = ACTION_ORDER.indexOf(action)
  return index === -1 ? ACTION_ORDER.length : index
}

export interface PermissionGroup {
  key: string
  label: string
  items: string[]
}

export function usePermissionLabels() {
  const { t, te } = useI18n()

  function resourceLabel(resource: string): string {
    const key = `permissions.resources.${resource}`
    return te(key) ? t(key) : humanize(resource)
  }

  function actionLabel(name: string): string {
    const action = name.split('.').slice(1).join(' ')
    const key = `permissions.actions.${action}`
    return te(key) ? t(key) : humanize(action) || name
  }

  function fullLabel(name: string): string {
    const resource = name.split('.')[0] ?? name
    return `${actionLabel(name)} ${resourceLabel(resource)}`
  }

  // Group permission names by their resource segment ("users.view" → "users"),
  // sorted by resource label with actions in the fixed view→manage order — so
  // the roles list, editor, and filters all present permissions the same way.
  function groupPermissionNames(names: string[]): PermissionGroup[] {
    const map: Record<string, string[]> = {}
    for (const name of names) {
      const key = name.split('.')[0] ?? name
      ;(map[key] ??= []).push(name)
    }
    return Object.entries(map)
      .map(([key, items]) => ({
        key,
        label: resourceLabel(key),
        items: items.slice().sort((a, b) => actionRank(a) - actionRank(b) || a.localeCompare(b))
      }))
      .sort((a, b) => a.label.localeCompare(b.label))
  }

  return { resourceLabel, actionLabel, fullLabel, groupPermissionNames }
}
