import type { Permission } from '~/types/rbac'

// Permissions are code-defined (see the backend Permission enum), so this is
// read-only: it powers the catalog page and the Roles editor's checkboxes.
export function usePermissions() {
  const api = useApi()

  return {
    list: () => api<Permission[]>('/permissions')
  }
}
