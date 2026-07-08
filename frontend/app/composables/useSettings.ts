import type { Setting, SettingValue } from '~/types/settings'

// Settings are code-defined (keys can't be added/removed from the UI); this
// only lists them and updates a value.
export function useSettings() {
  const api = useApi()

  return {
    list: () => api<Setting[]>('/settings'),
    update: (key: string, value: SettingValue) =>
      api<Setting>(`/settings/${key}`, { method: 'PUT', body: { value } })
  }
}
