import type { ComputedRef, WritableComputedRef } from 'vue'

// Lightweight global snackbar, the Vuetify equivalent of Nuxt UI's useToast.
// State lives in a shared useState so any page can trigger a message that the
// single <AppSnackbar> mounted in app.vue renders.
type SnackbarColor = 'success' | 'error' | 'info' | 'warning'

interface SnackbarState {
  show: boolean
  text: string
  color: SnackbarColor
}

interface UseSnackbar {
  // Writable so <AppSnackbar> can bind it with v-model (timeout/dismiss close).
  show: WritableComputedRef<boolean>
  text: ComputedRef<string>
  color: ComputedRef<SnackbarColor>
  notify: (text: string, color?: SnackbarColor) => void
}

export function useSnackbar(): UseSnackbar {
  const state = useState<SnackbarState>('snackbar', () => ({
    show: false,
    text: '',
    color: 'success'
  }))

  const show = computed({
    get: () => state.value.show,
    set: (value) => {
      state.value.show = value
    }
  })

  function notify(text: string, color: SnackbarColor = 'success') {
    state.value = { show: true, text, color }
  }

  return {
    show,
    text: computed(() => state.value.text),
    color: computed(() => state.value.color),
    notify
  }
}
