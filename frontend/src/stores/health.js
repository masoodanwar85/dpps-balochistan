import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useHealthStore = defineStore('health', () => {
  const status = ref('loading')
  const message = ref('')

  async function check() {
    status.value = 'loading'
    message.value = ''

    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/health`, {
        headers: {
          Accept: 'application/json',
        },
        credentials: 'include',
      })
      const body = await response.json()

      if (response.ok && body?.data?.status === 'ok' && body?.data?.database === 'ok') {
        status.value = 'ok'
        return
      }

      status.value = 'error'
      message.value = 'The API responded, but it is not ready.'
    } catch {
      status.value = 'error'
      message.value = 'Could not reach the API.'
    }
  }

  return { status, message, check }
})
