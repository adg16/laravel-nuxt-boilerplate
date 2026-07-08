// An application setting from GET /api/settings. Keys are code-defined; only
// the value is editable. `type` picks the input control; `options` lists the
// allowed values for a `select`.
export type SettingType = 'select' | 'toggle' | 'text'

// The value is typed per `type`: string for select/text, boolean for toggle.
export type SettingValue = string | boolean

export interface Setting {
  key: string
  value: SettingValue
  type: SettingType
  options: string[]
  group: string
}
