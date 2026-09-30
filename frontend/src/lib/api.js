const apiUrl = import.meta.env.VITE_API_URL

function xsrfToken() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

  return match ? decodeURIComponent(match[1]) : ''
}

export async function ensureCsrf() {
  await fetch(`${apiUrl}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}

export async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  if (method !== 'GET') {
    await ensureCsrf()
    headers['X-XSRF-TOKEN'] = xsrfToken()
  }

  const response = await fetch(`${apiUrl}${path}`, {
    method,
    headers,
    credentials: 'include',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  const payload = await response.json().catch(() => null)
  await sessionExpired(path, response)

  return { response, payload }
}

export async function upload(path, body) {
  await ensureCsrf()

  const response = await fetch(`${apiUrl}${path}`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': xsrfToken(),
    },
    body,
  })
  const payload = await response.json().catch(() => null)
  await sessionExpired(path, response)

  return { response, payload }
}

async function sessionExpired(path, response) {
  if (response.status !== 401 || path === '/api/v1/auth/login' || path === '/api/v1/auth/me') {
    return
  }

  const [{ default: router }, { useAuthStore }] = await Promise.all([
    import('@/router'),
    import('@/stores/auth'),
  ])
  const auth = useAuthStore()
  auth.user = null
  auth.ready = true

  if (router.currentRoute.value.name !== 'login') {
    await router.push({ name: 'login' })
  }
}

export function firstError(errors) {
  if (!errors || typeof errors !== 'object') {
    return ''
  }

  const messages = Object.values(errors).flat()

  return typeof messages[0] === 'string' ? messages[0] : ''
}
