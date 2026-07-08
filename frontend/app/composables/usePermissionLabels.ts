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

  return { resourceLabel, actionLabel, fullLabel }
}
