// Wraps the headless two-factor endpoints. TOTP enroll/QR/secret come from
// Fortify (`/user/two-factor-*`); email enroll and the recovery codes come from
// the app's own endpoints (recovery codes are method-agnostic — Fortify's
// require a TOTP secret an email user doesn't have). `confirm`/`confirmEmail`
// throw a 422 with errors on `code` for a wrong code.
export function useTwoFactor() {
  const api = useApi()

  return {
    // --- TOTP (Fortify) ---
    enable: () => api('/user/two-factor-authentication', { method: 'POST' }),
    qrCode: () => api<{ svg: string, url: string }>('/user/two-factor-qr-code'),
    secretKey: () => api<{ secretKey: string }>('/user/two-factor-secret-key'),
    confirm: (code: string) =>
      api('/user/confirmed-two-factor-authentication', { method: 'POST', body: { code } }),

    // --- Email ---
    enableEmail: () => api<{ recovery_codes: string[] }>('/user/two-factor-email', { method: 'POST' }),
    confirmEmail: (code: string) =>
      api('/user/two-factor-email/confirm', { method: 'POST', body: { code } }),
    resendEmailEnroll: () => api('/user/two-factor-email/resend', { method: 'POST' }),

    // --- Shared ---
    disable: () => api('/user/two-factor-authentication', { method: 'DELETE' }),
    recoveryCodes: () => api<string[]>('/user/two-factor/recovery-codes'),
    regenerateRecoveryCodes: () => api<string[]>('/user/two-factor/recovery-codes', { method: 'POST' })
  }
}
