import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, firstError } from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const ready = ref(false)
  const error = ref('')
  const codeRequired = ref(false)

  const permissions = computed(() => user.value?.permissions ?? [])

  function can(permission) {
    return permissions.value.includes(permission)
  }

  function canAny(list) {
    return list.some((permission) => can(permission))
  }

  async function fetchUser(force = false) {
    if (ready.value && !force) {
      return
    }

    try {
      const { response, payload } = await api('/api/v1/auth/me')
      user.value = response.ok ? payload?.data ?? null : null
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(email, password, code) {
    error.value = ''

    const body = { email, password }

    if (code) {
      body.code = code
    }

    try {
      const { response, payload } = await api('/api/v1/auth/login', {
        method: 'POST',
        body,
      })

      if (response.ok) {
        user.value = payload.data.user
        ready.value = true
        codeRequired.value = false
        error.value = ''

        return true
      }

      const errors = payload?.errors ?? {}
      codeRequired.value = Boolean(errors.code)
      error.value = firstError(errors) || 'Login failed.'

      return false
    } catch {
      error.value = 'Could not reach the API.'

      return false
    }
  }

  async function changePassword(currentPassword, password, passwordConfirmation) {
    error.value = ''

    try {
      const { response, payload } = await api('/api/v1/auth/password/change', {
        method: 'POST',
        body: {
          current_password: currentPassword,
          password,
          password_confirmation: passwordConfirmation,
        },
      })

      if (response.ok) {
        user.value = payload.data
        error.value = ''

        return { ok: true, errors: {} }
      }

      return { ok: false, errors: payload?.errors ?? {} }
    } catch {
      return { ok: false, errors: { password: ['Could not reach the API.'] } }
    }
  }

  async function logout() {
    try {
      await api('/api/v1/auth/logout', { method: 'POST', body: {} })
    } catch {
      // The local session is cleared even when the API cannot be reached.
    }

    user.value = null
    codeRequired.value = false
    error.value = ''
  }

  return {
    user,
    ready,
    error,
    codeRequired,
    permissions,
    can,
    canAny,
    fetchUser,
    login,
    changePassword,
    logout,
  }
})
