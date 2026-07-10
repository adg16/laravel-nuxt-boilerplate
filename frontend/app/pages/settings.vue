<script setup lang="ts">
import { PERMISSIONS } from '~/constants/permissions'
import type { Setting, SettingValue } from '~/types/settings'

definePageMeta({
  breadcrumb: 'nav.settings',
  permission: PERMISSIONS.SettingsView
})

const { t, te } = useI18n()
const { notify } = useSnackbar()
const { can } = useAuthz()
const config = useConfigStore()
const settingsApi = useSettings()

const canManage = computed(() => can(PERMISSIONS.SettingsManage))

const settings = ref<Setting[]>([])
const edited = reactive<Record<string, SettingValue>>({})
const savingKey = ref<string | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    settings.value = await settingsApi.list()
    for (const setting of settings.value) edited[setting.key] = setting.value
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Group settings by their `group` for sectioned display.
const groups = computed(() => {
  const grouped: Record<string, Setting[]> = {}
  for (const setting of settings.value) (grouped[setting.group] ??= []).push(setting)
  return Object.entries(grouped).map(([name, items]) => ({ name, items }))
})

function isDirty(setting: Setting): boolean {
  return edited[setting.key] !== setting.value
}

// Labels come from i18n keys (settings.items / .options / .groups); fall back to
// the raw key so a newly added setting still renders before its copy exists.
function label(key: string): string {
  const k = `settings.items.${key}.label`
  return te(k) ? t(k) : key
}
function description(key: string): string {
  const k = `settings.items.${key}.description`
  return te(k) ? t(k) : ''
}
function groupLabel(group: string): string {
  const k = `settings.groups.${group}`
  return te(k) ? t(k) : group
}
function optionItems(setting: Setting) {
  return setting.options.map((value) => {
    const k = `settings.options.${setting.key}.${value}`
    return { value, title: te(k) ? t(k) : value }
  })
}

async function save(setting: Setting) {
  savingKey.value = setting.key
  try {
    const updated = await settingsApi.update(setting.key, edited[setting.key] ?? setting.value)
    const index = settings.value.findIndex(s => s.key === setting.key)
    if (index !== -1) settings.value[index] = updated
    edited[setting.key] = updated.value
    notify(t('settings.saved'))
    // Keep the cached app config in sync so dependent screens react at once.
    if (setting.key === 'user_creation_mode') await config.fetch()
  } catch (e) {
    notify(apiErrorMessage(e), 'error')
  } finally {
    savingKey.value = null
  }
}
</script>

<template>
  <div>
    <AppPageHeader
      v-if="!canManage"
      :description="$t('settings.readonlyHint')"
    />

    <div
      v-if="loading"
      class="d-flex justify-center py-8"
    >
      <v-progress-circular
        indeterminate
        size="28"
      />
    </div>

    <template v-else>
      <v-card
        v-for="group in groups"
        :key="group.name"
        border
        flat
        class="mb-6"
      >
        <v-card-item>
          <v-card-title class="text-title-large">
            {{ groupLabel(group.name) }}
          </v-card-title>
        </v-card-item>
        <v-divider />
        <v-card-text class="d-flex flex-column ga-6 pt-4">
          <div
            v-for="setting in group.items"
            :key="setting.key"
          >
            <div class="text-title-small">
              {{ label(setting.key) }}
            </div>
            <div
              v-if="description(setting.key)"
              class="text-body-small text-medium-emphasis mb-3"
            >
              {{ description(setting.key) }}
            </div>

            <div class="d-flex flex-wrap align-center ga-3">
              <v-select
                v-if="setting.type === 'select'"
                v-model="edited[setting.key]"
                :items="optionItems(setting)"
                :disabled="!canManage"
                density="comfortable"
                hide-details
                class="settings-control"
              />
              <v-switch
                v-else-if="setting.type === 'toggle'"
                v-model="edited[setting.key]"
                :label="edited[setting.key] ? $t('settings.on') : $t('settings.off')"
                :disabled="!canManage"
                color="primary"
                hide-details
              />
              <v-text-field
                v-else
                v-model="edited[setting.key]"
                :disabled="!canManage"
                density="comfortable"
                hide-details
                class="settings-control"
              />

              <Can :permission="PERMISSIONS.SettingsManage">
                <v-btn
                  color="primary"
                  variant="flat"
                  :disabled="!isDirty(setting)"
                  :loading="savingKey === setting.key"
                  @click="save(setting)"
                >
                  {{ $t('common.save') }}
                </v-btn>
              </Can>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </template>
  </div>
</template>

<style scoped>
.settings-control {
  max-width: 360px;
}
</style>
