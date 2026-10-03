<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, ensureCsrf, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const canExport = computed(() => auth.can('exports.run'))
const canIssue = computed(() => auth.can('licenses.issue'))
const canSuspend = computed(() => auth.can('licenses.suspend'))
const canCancel = computed(() => auth.can('licenses.cancel'))
const canRestore = computed(() => auth.can('licenses.restore'))

const rows = ref([])
const meta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const districts = ref([])
const entity = ref('')
const kind = ref('')
const status = ref('')
const districtId = ref('')
const validFrom = ref('')
const validTo = ref('')
const search = ref('')
const issuedYear = ref('')
const documents = ref('')
const page = ref(1)
const loadError = ref('')
const notice = ref('')
const saving = ref(false)
const action = ref(null)
const actionForm = ref({ reason: '', order_no: '', effective_date: '' })

const statuses = [
  ['', 'All'],
  ['active', 'Active'],
  ['expired', 'Expired'],
  ['suspended', 'Suspended'],
  ['cancelled', 'Cancelled'],
  ['superseded', 'Superseded'],
]

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function label(value) {
  return ({
    company: 'Company',
    dealer: 'Dealer',
    registration: 'Registration',
    renewal: 'Renewal',
    restoration: 'Restoration',
    active: 'Active',
    expired: 'Expired',
    suspended: 'Suspended',
    cancelled: 'Cancelled',
    superseded: 'Superseded',
    complete: 'Complete',
    incomplete: 'Incomplete',
    not_applicable: 'N/A',
  })[value] || value
}

function queryString() {
  const params = new URLSearchParams({ page: String(page.value), per_page: '25' })

  if (entity.value) {
    params.set('filter[licensable_type]', entity.value)
  }

  if (kind.value) {
    params.set('filter[license_kind]', kind.value)
  }

  if (status.value) {
    params.set('filter[status]', status.value)
  }

  if (districtId.value) {
    params.set('filter[district_id]', districtId.value)
  }

  if (validFrom.value) {
    params.set('filter[valid_from]', validFrom.value)
  }

  if (validTo.value) {
    params.set('filter[valid_to]', validTo.value)
  }

  if (issuedYear.value) {
    params.set('filter[issued_year]', issuedYear.value)
  }

  if (documents.value) {
    params.set('filter[documents_status]', documents.value)
  }

  if (search.value.trim()) {
    params.set('search', search.value.trim())
  }

  return params.toString()
}

async function load() {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/licenses?${queryString()}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The list could not be loaded.'
    return
  }

  rows.value = payload.data || []
  meta.value = payload.meta || meta.value
}

async function loadDistricts() {
  const { response, payload } = await api('/api/v1/districts?per_page=100&sort=name')
  districts.value = response.ok ? payload.data || [] : []
}

function canChange(row, name) {
  if (name === 'suspend') {
    return canSuspend.value && ['active', 'expired'].includes(row.status)
  }

  if (name === 'cancel') {
    return canCancel.value && ['active', 'expired', 'suspended'].includes(row.status)
  }

  return canRestore.value && ['suspended', 'cancelled'].includes(row.status)
}

function startAction(row, name) {
  notice.value = ''
  loadError.value = ''
  action.value = { id: row.id, name, license_no: row.license_no }
  actionForm.value = { reason: '', order_no: '', effective_date: new Date().toISOString().slice(0, 10) }
}

async function submitAction() {
  if (!action.value) {
    return
  }

  saving.value = true
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/licenses/${action.value.id}/${action.value.name}`, {
    method: 'POST',
    body: actionForm.value,
  })
  saving.value = false

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The license could not be changed.'
    return
  }

  notice.value = `${label(payload.data.status)} — ${payload.data.license_no}`
  action.value = null
  await load()
}

async function certificate(row) {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/licenses/${row.id}/certificate`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The certificate could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank')
}

function openRow(row) {
  if (row.application_id) {
    router.push({ name: 'application-detail', params: { id: row.application_id } })
    return
  }

  const name = row.licensable_type === 'dealer' ? 'dealer-profile' : 'company-profile'
  router.push({ name, params: { id: row.licensable_id } })
}

async function exportExcel() {
  loadError.value = ''
  await ensureCsrf()
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/exports/licenses?${queryString()}`, {
    credentials: 'include',
  })

  if (!response.ok) {
    loadError.value = 'Could not export licenses.'
    return
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'licenses.xlsx'
  link.click()
  URL.revokeObjectURL(url)
}

function applyListQuery() {
  if (typeof route.query.kind === 'string') {
    kind.value = route.query.kind
  }

  if (typeof route.query.status === 'string') {
    status.value = route.query.status
  }

  issuedYear.value = typeof route.query.year === 'string' ? route.query.year : ''
  documents.value = typeof route.query.documents === 'string' ? route.query.documents : ''
}

watch(() => [route.query.kind, route.query.status, route.query.year, route.query.documents], () => {
  applyListQuery()
  page.value = 1
  load()
})

onMounted(async () => {
  applyListQuery()
  await Promise.all([load(), loadDistricts()])
})
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <PageSection accent="violet" eyebrow="Issuance" title="Licenses">
      <template #actions>
        <Button v-if="canIssue" type="button" size="sm" @click="router.push({ name: 'previous-license' })">Record previous license</Button>
        <Button v-if="canExport" type="button" variant="outline" size="sm" @click="exportExcel">Export Excel</Button>
      </template>
      <div class="flex flex-wrap items-end gap-3">
        <div class="dpps-field">
          <Label for="lic-search">License no</Label>
          <Input id="lic-search" v-model="search" aria-label="License no" @keyup.enter="page = 1; load()" />
        </div>
        <div class="dpps-field">
          <Label for="lic-type">Type</Label>
          <select id="lic-type" v-model="entity" class="dpps-select" aria-label="Type" @change="page = 1; load()">
            <option value="">All</option>
            <option value="company">Company</option>
            <option value="dealer">Dealer</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="lic-kind">Kind</Label>
          <select id="lic-kind" v-model="kind" class="dpps-select" aria-label="Kind" @change="page = 1; load()">
            <option value="">All</option>
            <option value="registration">Registration</option>
            <option value="renewal">Renewal</option>
            <option value="restoration">Restoration</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="lic-status">Status</Label>
          <select id="lic-status" v-model="status" class="dpps-select" aria-label="Status" @change="page = 1; load()">
            <option v-for="[value, name] in statuses" :key="value" :value="value">{{ name }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="lic-district">District</Label>
          <select id="lic-district" v-model="districtId" class="dpps-select" aria-label="District" @change="page = 1; load()">
            <option value="">All</option>
            <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="lic-from">Valid from</Label>
          <Input id="lic-from" v-model="validFrom" type="date" aria-label="Valid from" @change="page = 1; load()" />
        </div>
        <div class="dpps-field">
          <Label for="lic-to">Valid to</Label>
          <Input id="lic-to" v-model="validTo" type="date" aria-label="Valid to" @change="page = 1; load()" />
        </div>
        <Button type="button" variant="outline" @click="page = 1; load()">Search</Button>
      </div>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>License No</th>
              <th>Applicant</th>
              <th>Type</th>
              <th>Kind</th>
              <th>District</th>
              <th>Valid from</th>
              <th>Valid to</th>
              <th>Status</th>
              <th>Documents</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.license_no }}</td>
              <td>{{ row.applicant_name }}</td>
              <td><StatusBadge :value="row.licensable_type" :label="label(row.licensable_type)" /></td>
              <td><StatusBadge :value="row.license_kind" :label="label(row.license_kind)" /></td>
              <td>{{ row.district_name || '—' }}</td>
              <td class="tabular-nums">{{ displayDate(row.valid_from) }}</td>
              <td class="tabular-nums">{{ displayDate(row.valid_to) }}</td>
              <td><StatusBadge :value="row.status" :label="label(row.status)" /></td>
              <td><StatusBadge :value="row.documents_status" :label="label(row.documents_status)" /></td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" @click="openRow(row)">View</Button>
                  <Button type="button" variant="outline" size="sm" @click="certificate(row)">Certificate</Button>
                  <Button v-if="canChange(row, 'suspend')" type="button" variant="outline" size="sm" @click="startAction(row, 'suspend')">Suspend</Button>
                  <Button v-if="canChange(row, 'cancel')" type="button" variant="outline" size="sm" @click="startAction(row, 'cancel')">Cancel</Button>
                  <Button v-if="canChange(row, 'restore')" type="button" variant="outline" size="sm" @click="startAction(row, 'restore')">Restore</Button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td class="text-muted-foreground" colspan="10">No licenses.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>

    <PageSection v-if="action" accent="orange" eyebrow="Action" :title="`${action.name === 'suspend' ? 'Suspend' : action.name === 'cancel' ? 'Cancel' : 'Restore'} ${action.license_no}`">
      <form class="grid max-w-xl gap-4" @submit.prevent="submitAction">
        <div class="dpps-field">
          <Label for="lic-reason">Reason</Label>
          <Input id="lic-reason" v-model="actionForm.reason" required minlength="3" aria-label="Reason" />
        </div>
        <div class="dpps-field">
          <Label for="lic-order">Order number</Label>
          <Input id="lic-order" v-model="actionForm.order_no" required aria-label="Order number" />
        </div>
        <div class="dpps-field">
          <Label for="lic-effective">Effective date</Label>
          <Input id="lic-effective" v-model="actionForm.effective_date" type="date" required aria-label="Effective date" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="action = null">Cancel</Button>
        </div>
      </form>
    </PageSection>

    <div class="flex items-center justify-between text-sm">
      <p class="text-muted-foreground">Showing {{ meta.total }}</p>
      <div class="flex gap-2">
        <Button type="button" variant="outline" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
        <Button type="button" variant="outline" :disabled="page >= meta.last_page" @click="page += 1; load()">Next</Button>
      </div>
    </div>
  </div>
</template>
