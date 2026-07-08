import { defineStore } from 'pinia'
import type { User } from '~/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const api = useApi()

  async function getCsrfCookie() {
    await $fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  }

  async function fetchUser() {
    try {
      user.value = await api<User>('/user')
    } catch {
      user.value = null
    }
  }

  async function login(credentials: { email: string, password: string }) {
    await getCsrfCookie()
    user.value = await api<User>('/login', { method: 'POST', body: credentials })
  }

  async function register(payload: { name: string, email: string, password: string, password_confirmation: string }) {
    await getCsrfCookie()
    user.value = await api<User>('/register', { method: 'POST', body: payload })
  }

  async function logout() {
    await api('/logout', { method: 'POST' })
    user.value = null
  }

  async function forgotPassword(email: string): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/forgot-password', {
      method: 'POST',
      body: { email }
    })
    return message
  }

  async function resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/reset-password', {
      method: 'POST',
      body: payload
    })
    return message
  }

  async function acceptInvitation(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<string> {
    await getCsrfCookie()
    const { message } = await api<{ message: string }>('/accept-invitation', {
      method: 'POST',
      body: payload
    })
    return message
  }

  return { user, getCsrfCookie, fetchUser, login, register, logout, forgotPassword, resetPassword, acceptInvitation }
})
