<script setup>
import { computed, onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { api, ensureCsrf, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canExport = computed(() => auth.can('exports.run') && auth.can('activity_logs.view'))

const rows = ref([])
const meta = ref({
  current_page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
  users: [],
  actions: [],
  modules: [],
  has_system: false,
})
const userId = ref('')
const action = ref('')
const moduleFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const page = ref(1)
const loadError = ref('')
const openId = ref(null)

const showing = computed(() => {
  if (!meta.value.total) {
    return 'Showing 0 of 0'
  }

  const from = (meta.value.current_page - 1) * meta.value.per_page + 1
  const to = Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)

  return `Showing ${from}–${to} of ${meta.value.total}`
})

const userOptions = computed(() => {
  const options = [{ value: '', label: 'All' }]

  if (meta.value.has_system) {
    options.push({ value: 'system', label: 'System' })
  }

  for (const user of meta.value.users ?? []) {
    options.push({ value: String(user.id), label: user.name })
  }

  return options
})

function queryString() {
  const params = new URLSearchParams()

  if (userId.value) {
    params.set('user_id', userId.value)
  }

  if (action.value) {
    params.set('action', action.value)
  }

  if (moduleFilter.value) {
    params.set('module', moduleFilter.value)
  }

  if (dateFrom.value) {
    params.set('from', dateFrom.value)
  }

  if (dateTo.value) {
    params.set('to', dateTo.value)
  }

  params.set('page', String(page.value))
  params.set('per_page', '25')

  return params.toString()
}

function displayDateTime(value) {
  if (!value) {
    return '—'
  }

  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/)

  if (!match) {
    return value
  }

  const [, year, month, day, hour, minute] = match

  return `${day}-${month}-${year} ${hour}:${minute}`
}

function moduleLabel(value) {
  return String(value).replaceAll('_', ' ')
}

function cell(value) {
  return value === null || value === undefined || value === '' ? '—' : value
}

async function load() {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/activity-logs?${queryString()}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load the activity log.'
    rows.value = []

    return
  }

  rows.value = payload.data ?? []
  meta.value = payload.meta ?? meta.value

  if (openId.value !== null && !rows.value.some((row) => row.id === openId.value)) {
    openId.value = null
  }
}

function applyFilters() {
  page.value = 1
  openId.value = null
  load()
}

function toggleDetails(id) {
  openId.value = openId.value === id ? null : id
}

async function exportExcel() {
  loadError.value = ''
  await ensureCsrf()
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/exports/activity-logs?${queryString()}`, {
    credentials: 'include',
  })

  if (!response.ok) {
    loadError.value = 'Could not export the activity log.'

    return
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'activity-logs.xlsx'
  link.click()
  URL.revokeObjectURL(url)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <PageSection accent="slate" eyebrow="Audit" title="Activity log">
      <template #actions>
        <Button v-if="canExport" type="button" variant="outline" size="sm" @click="exportExcel">Export Excel</Button>
      </template>
      <div class="flex flex-wrap items-end gap-3">
        <div class="dpps-field">
          <Label for="log-user">User</Label>
          <select id="log-user" v-model="userId" class="dpps-select" aria-label="User" @change="applyFilters">
            <option v-for="option in userOptions" :key="option.value || 'all-users'" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="log-action">Action</Label>
          <select id="log-action" v-model="action" class="dpps-select" aria-label="Action" @change="applyFilters">
            <option value="">All</option>
            <option v-for="item in meta.actions" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="log-module">Module</Label>
          <select id="log-module" v-model="moduleFilter" class="dpps-select" aria-label="Module" @change="applyFilters">
            <option value="">All</option>
            <option v-for="item in meta.modules" :key="item" :value="item">{{ moduleLabel(item) }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="log-from">Date from</Label>
          <input id="log-from" v-model="dateFrom" type="date" class="dpps-select" aria-label="Date from" @change="applyFilters">
        </div>
        <div class="dpps-field">
          <Label for="log-to">Date to</Label>
          <input id="log-to" v-model="dateTo" type="date" class="dpps-select" aria-label="Date to" @change="applyFilters">
        </div>
      </div>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>User</th>
              <th>Action</th>
              <th>Record</th>
              <th>IP</th>
              <th><span class="sr-only">Details</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td class="text-muted-foreground" colspan="6">No activity.</td>
            </tr>
            <template v-for="row in rows" :key="row.id">
              <tr>
                <td class="whitespace-nowrap tabular-nums">{{ displayDateTime(row.created_at) }}</td>
                <td>{{ row.user_name }}</td>
                <td>{{ row.action }}</td>
                <td>{{ row.record }}</td>
                <td>{{ row.ip_address }}</td>
                <td class="text-right">
                  <Button type="button" variant="outline" size="sm" :aria-expanded="openId === row.id" :aria-label="`Details for ${row.record}`" @click="toggleDetails(row.id)">
                    Details
                  </Button>
                </td>
              </tr>
              <tr v-if="openId === row.id" class="bg-muted/40 hover:bg-muted/40">
                <td class="px-3 py-3" colspan="6">
                  <p v-if="row.reason" class="mb-2 text-sm">Reason: {{ row.reason }}</p>
                  <table v-if="row.changes.length" class="w-full text-left text-sm">
                    <thead class="bg-transparent text-muted-foreground normal-case tracking-normal">
                      <tr>
                        <th class="px-0 py-1 pr-3 font-medium">Field</th>
                        <th class="px-0 py-1 pr-3 font-medium">Old value</th>
                        <th class="px-0 py-1 font-medium">New value</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="change in row.changes" :key="change.field" class="border-0 hover:bg-transparent">
                        <td class="px-0 py-1 pr-3">{{ change.field }}</td>
                        <td class="px-0 py-1 pr-3">{{ cell(change.old) }}</td>
                        <td class="px-0 py-1">{{ cell(change.new) }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-else-if="!row.reason" class="text-muted-foreground">No field changes.</p>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </PageSection>

    <div class="flex items-center justify-between text-sm">
      <p class="text-muted-foreground">{{ showing }}</p>
      <div class="flex gap-2">
        <Button type="button" variant="outline" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
        <Button type="button" variant="outline" :disabled="page >= meta.last_page" @click="page += 1; load()">Next</Button>
      </div>
    </div>
  </div>
</template>
