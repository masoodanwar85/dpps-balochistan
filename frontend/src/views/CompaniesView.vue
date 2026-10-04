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
const canCreate = computed(() => auth.can('companies.create'))
const canUpdate = computed(() => auth.can('companies.update'))
const canDelete = computed(() => auth.can('companies.delete'))
const canExport = computed(() => auth.can('exports.run'))

const legalTypes = [
  ['private_ltd', 'Private Ltd'],
  ['public_ltd', 'Public Ltd'],
  ['partnership', 'Partnership'],
  ['sole_proprietor', 'Sole proprietor'],
  ['other', 'Other'],
]
const statuses = [
  ['', 'All'],
  ['unlicensed', 'Unlicensed'],
  ['active', 'Active'],
  ['expiring', 'Expiring'],
  ['expired', 'Expired'],
  ['suspended', 'Suspended'],
  ['cancelled', 'Cancelled'],
]

const rows = ref([])
const meta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const provinces = ref([])
const search = ref('')
const status = ref('')
const pcpa = ref('')
const expiry = ref('')
const page = ref(1)
const loadError = ref('')
const mode = ref('list')
const company = ref(null)
const form = ref(null)
const formError = ref('')
const warnings = ref([])
const matches = ref([])
const ntnTaken = ref(false)
const warningReason = ref('')
const deleteReason = ref('')
const notice = ref('')
const saving = ref(false)
let checkTimer = 0

const showing = computed(() => {
  if (!meta.value.total) {
    return 'Showing 0 of 0'
  }

  const from = (meta.value.current_page - 1) * meta.value.per_page + 1
  const to = Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)

  return `Showing ${from}–${to} of ${meta.value.total}`
})

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).split('-')

  return `${day}-${month}-${year}`
}

function statusLabel(value) {
  return statuses.find(([key]) => key === value)?.[1] || value
}

function legalLabel(value) {
  return legalTypes.find(([key]) => key === value)?.[1] || value
}

function blankForm() {
  return {
    name: '',
    legal_type: 'private_ltd',
    ntn: '',
    incorporation_no: '',
    incorporation_date: '',
    head_office_address: '',
    city: '',
    province_id: '',
    landline: '',
    mobile: '',
    email: '',
    website: '',
    pcpa_member: false,
    croplife_member: false,
    csr: false,
    rnd: false,
    membership_no: '',
  }
}

function formFrom(record) {
  return {
    name: record.name ?? '',
    legal_type: record.legal_type ?? 'private_ltd',
    ntn: record.ntn ?? '',
    incorporation_no: record.incorporation_no ?? '',
    incorporation_date: record.incorporation_date ?? '',
    head_office_address: record.head_office_address ?? '',
    city: record.city ?? '',
    province_id: record.province_id != null ? String(record.province_id) : '',
    landline: record.landline ?? '',
    mobile: record.mobile ?? '',
    email: record.email ?? '',
    website: record.website ?? '',
    pcpa_member: !!record.pcpa_member,
    croplife_member: !!record.croplife_member,
    csr: !!record.csr,
    rnd: !!record.rnd,
    membership_no: record.membership_no ?? '',
  }
}

function emptyToNull(value) {
  const text = typeof value === 'string' ? value.trim() : value

  return text === '' || text === undefined ? null : text
}

function queryString() {
  const params = new URLSearchParams()
  params.set('page', String(page.value))
  params.set('per_page', '25')

  if (search.value.trim()) {
    params.set('search', search.value.trim())
  }

  if (status.value) {
    params.set('filter[status]', status.value)
  }

  if (pcpa.value) {
    params.set('filter[pcpa_member]', pcpa.value)
  }

  if (expiry.value) {
    params.set('filter[expiry]', expiry.value)
  }

  return params.toString()
}

async function load() {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/companies?${queryString()}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load companies.'

    return
  }

  rows.value = payload.data
  meta.value = payload.meta
  provinces.value = payload.meta.provinces ?? provinces.value
}

function applyFilters() {
  page.value = 1
  load()
}

function startCreate() {
  company.value = null
  form.value = blankForm()
  warnings.value = []
  matches.value = []
  ntnTaken.value = false
  warningReason.value = ''
  deleteReason.value = ''
  formError.value = ''
  notice.value = ''
  mode.value = 'form'
}

function openCompany(row) {
  router.push(`/companies/${row.id}`)
}

async function openEditor(id) {
  formError.value = ''
  notice.value = ''

  if (provinces.value.length === 0) {
    await load()
  }

  const { response, payload } = await api(`/api/v1/companies/${id}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this company.'
    mode.value = 'list'

    return
  }

  company.value = payload.data
  form.value = formFrom(payload.data)
  provinces.value = payload.meta?.provinces ?? provinces.value
  warnings.value = []
  matches.value = []
  ntnTaken.value = false
  warningReason.value = ''
  deleteReason.value = ''
  mode.value = 'form'
}

function leaveForm() {
  if (company.value?.id) {
    router.push(`/companies/${company.value.id}`)

    return
  }

  mode.value = 'list'
  router.replace({ name: 'companies' })
}

function scheduleCheck() {
  clearTimeout(checkTimer)
  checkTimer = setTimeout(runCheck, 400)
}

async function runCheck() {
  if (!form.value || (!canCreate.value && !canUpdate.value)) {
    return
  }

  const { response, payload } = await api('/api/v1/companies/check-duplicate', {
    method: 'POST',
    body: {
      name: form.value.name,
      ntn: emptyToNull(form.value.ntn),
      ignore_id: company.value?.id ?? null,
    },
  })

  if (!response.ok) {
    return
  }

  warnings.value = payload.data.warnings ?? []
  matches.value = payload.data.matches ?? []
  ntnTaken.value = !!payload.data.ntn_taken
}

function payload(confirm) {
  const body = {
    name: form.value.name,
    legal_type: form.value.legal_type,
    ntn: emptyToNull(form.value.ntn),
    incorporation_no: emptyToNull(form.value.incorporation_no),
    incorporation_date: emptyToNull(form.value.incorporation_date),
    head_office_address: form.value.head_office_address,
    city: form.value.city,
    province_id: Number(form.value.province_id),
    landline: emptyToNull(form.value.landline),
    mobile: emptyToNull(form.value.mobile),
    email: emptyToNull(form.value.email),
    website: emptyToNull(form.value.website),
    pcpa_member: form.value.pcpa_member,
    croplife_member: form.value.croplife_member,
    membership_no: emptyToNull(form.value.membership_no),
    csr: form.value.csr,
    rnd: form.value.rnd,
  }

  if (confirm) {
    body.confirm_warnings = true
    body.warning_reason = warningReason.value
  }

  return body
}

async function save(confirm = false) {
  formError.value = ''
  notice.value = ''
  saving.value = true
  const path = company.value ? `/api/v1/companies/${company.value.id}` : '/api/v1/companies'
  const { response, payload: result } = await api(path, {
    method: company.value ? 'PUT' : 'POST',
    body: payload(confirm),
  })
  saving.value = false

  if (response.status === 409) {
    warnings.value = result?.warnings ?? []

    return
  }

  if (!response.ok) {
    formError.value = firstError(result?.errors) || 'Could not save this company.'

    return
  }

  notice.value = ''
  await router.push(`/companies/${result.data.id}`)
}

async function removeCompany() {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/companies/${company.value.id}`, {
    method: 'DELETE',
    body: { reason: deleteReason.value },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not delete this company.'

    return
  }

  mode.value = 'list'
  notice.value = ''
  await router.replace({ name: 'companies' })
  await load()
}

async function exportExcel() {
  loadError.value = ''
  await ensureCsrf()
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/exports/companies?${queryString()}`, {
    credentials: 'include',
  })

  if (!response.ok) {
    loadError.value = 'Could not export companies.'

    return
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'companies.xlsx'
  link.click()
  URL.revokeObjectURL(url)
}

async function viewMatch(match) {
  await openCompany(match)
}

function applyListQuery() {
  status.value = typeof route.query.status === 'string' ? route.query.status : ''
}

watch(() => route.query.status, () => {
  applyListQuery()

  if (!route.query.edit) {
    page.value = 1
    load()
  }
})

onMounted(async () => {
  applyListQuery()
  await load()

  if (route.query.edit) {
    await openEditor(route.query.edit)
  }
})
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="mode === 'list'">
      <PageSection accent="emerald" eyebrow="Register" title="Companies">
        <template #actions>
          <Button v-if="canExport" type="button" variant="outline" size="sm" @click="exportExcel">Export Excel</Button>
          <Button v-if="canCreate" type="button" size="sm" @click="startCreate">New Company</Button>
        </template>
        <div class="flex flex-wrap items-end gap-3">
          <div class="dpps-field">
            <Label for="company-search">Search</Label>
            <Input id="company-search" v-model="search" class="w-56" placeholder="Name, code, or NTN" aria-label="Search" @keyup.enter="applyFilters" />
          </div>
          <div class="dpps-field">
            <Label for="status-filter">Status</Label>
            <select id="status-filter" v-model="status" class="dpps-select" aria-label="Status" @change="applyFilters">
              <option v-for="[value, label] in statuses" :key="value || 'all'" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="pcpa-filter">PCPA</Label>
            <select id="pcpa-filter" v-model="pcpa" class="dpps-select" aria-label="PCPA" @change="applyFilters">
              <option value="">All</option>
              <option value="true">Yes</option>
              <option value="false">No</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="expiry-filter">Expiry</Label>
            <select id="expiry-filter" v-model="expiry" class="dpps-select" aria-label="Expiry" @change="applyFilters">
              <option value="">All</option>
              <option value="expiring">Expiring</option>
              <option value="expired">Expired</option>
            </select>
          </div>
          <Button type="button" variant="outline" @click="applyFilters">Search</Button>
        </div>
      </PageSection>

      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>NTN</th>
                <th>Expiry</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td class="text-muted-foreground" colspan="5">No companies.</td>
              </tr>
              <tr v-for="row in rows" :key="row.id" class="dpps-row-link" @click="openCompany(row)">
                <td class="font-medium">{{ row.company_code }}</td>
                <td>{{ row.name }}</td>
                <td>{{ row.ntn || '—' }}</td>
                <td class="tabular-nums">{{ displayDate(row.expiry_date) }}</td>
                <td><StatusBadge :value="row.status" :label="statusLabel(row.status)" /></td>
              </tr>
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
    </template>

    <form v-else-if="form" class="grid gap-6" @submit.prevent="save(false)">
      <PageSection accent="emerald" eyebrow="Register" :title="company ? company.name : 'New company'">
        <template #actions>
          <Button type="button" variant="outline" size="sm" @click="leaveForm">Back</Button>
        </template>
        <div v-if="company" class="mb-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
          <span>{{ company.company_code }}</span>
          <StatusBadge :value="company.status" :label="statusLabel(company.status)" />
          <span v-if="company.expiry_date">Expiry {{ displayDate(company.expiry_date) }}</span>
        </div>

        <Alert v-if="formError" variant="destructive" class="mb-4">
          <AlertTitle>{{ formError }}</AlertTitle>
        </Alert>
        <Alert v-if="notice" class="mb-4">
          <AlertTitle>{{ notice }}</AlertTitle>
        </Alert>
        <Alert v-if="ntnTaken" variant="destructive" class="mb-4">
          <AlertTitle>This NTN already exists.</AlertTitle>
        </Alert>
        <Alert v-for="match in matches" :key="match.id" class="mb-4">
          <AlertTitle>
            {{ match.message }}
            <button type="button" class="ml-2 underline" @click="viewMatch(match)">View it</button>
          </AlertTitle>
        </Alert>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="dpps-field sm:col-span-2">
            <Label for="company-name">Name</Label>
            <Input id="company-name" v-model="form.name" required :readonly="!canCreate && !canUpdate" aria-label="Name" @input="scheduleCheck" />
          </div>
          <div class="dpps-field">
            <Label for="legal-type">Legal type</Label>
            <select id="legal-type" v-model="form.legal_type" class="dpps-select" aria-label="Legal type" :disabled="!canCreate && !canUpdate">
              <option v-for="[value, label] in legalTypes" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="ntn">NTN</Label>
            <Input id="ntn" v-model="form.ntn" :readonly="!canCreate && !canUpdate" aria-label="NTN" @input="scheduleCheck" />
          </div>
          <div class="dpps-field">
            <Label for="incorporation-no">Incorporation no</Label>
            <Input id="incorporation-no" v-model="form.incorporation_no" :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="incorporation-date">Incorporation date</Label>
            <Input id="incorporation-date" v-model="form.incorporation_date" type="date" :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field sm:col-span-2">
            <Label for="address">Head office address</Label>
            <textarea id="address" v-model="form.head_office_address" rows="2" class="dpps-textarea" required :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="city">City</Label>
            <Input id="city" v-model="form.city" required :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="province">Province</Label>
            <select id="province" v-model="form.province_id" class="dpps-select" required aria-label="Province" :disabled="!canCreate && !canUpdate">
              <option value="">Choose</option>
              <option v-for="province in provinces" :key="province.id" :value="String(province.id)">{{ province.name }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="landline">Landline</Label>
            <Input id="landline" v-model="form.landline" :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="company-mobile">Mobile</Label>
            <Input id="company-mobile" v-model="form.mobile" :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="company-email">Email</Label>
            <Input id="company-email" v-model="form.email" type="email" :readonly="!canCreate && !canUpdate" />
          </div>
          <div class="dpps-field">
            <Label for="website">Website</Label>
            <Input id="website" v-model="form.website" :readonly="!canCreate && !canUpdate" />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.pcpa_member" type="checkbox" :disabled="!canCreate && !canUpdate" aria-label="PCPA member">
            PCPA
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.croplife_member" type="checkbox" :disabled="!canCreate && !canUpdate" aria-label="CropLife member">
            CropLife
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.csr" type="checkbox" :disabled="!canCreate && !canUpdate" aria-label="CSR">
            CSR
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.rnd" type="checkbox" :disabled="!canCreate && !canUpdate" aria-label="R&D">
            R&amp;D
          </label>
          <div class="dpps-field sm:col-span-2">
            <Label for="membership-no">Membership no</Label>
            <Input id="membership-no" v-model="form.membership_no" :readonly="!canCreate && !canUpdate" />
          </div>
        </div>

        <div v-if="warnings.length" class="mt-4 grid gap-2">
          <Alert>
            <AlertTitle>{{ warnings.join(' ') }} Enter a reason to continue.</AlertTitle>
          </Alert>
          <div class="dpps-field">
            <Label for="warning-reason">Reason</Label>
            <textarea id="warning-reason" v-model="warningReason" rows="2" class="dpps-textarea" aria-label="Warning reason" />
          </div>
        </div>

        <div v-if="canCreate || canUpdate" class="mt-5 flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button v-if="warnings.length" type="button" variant="outline" :disabled="saving" @click="save(true)">Confirm and save</Button>
        </div>
      </PageSection>

      <PageSection v-if="company && canDelete" accent="rose" eyebrow="Danger zone" title="Delete company">
        <div class="grid max-w-lg gap-3">
          <div class="dpps-field">
            <Label for="delete-reason">Delete reason</Label>
            <textarea id="delete-reason" v-model="deleteReason" rows="2" class="dpps-textarea" aria-label="Delete reason" />
          </div>
          <div>
            <Button type="button" variant="outline" :disabled="saving" @click="removeCompany">Delete</Button>
          </div>
        </div>
      </PageSection>

      <p v-if="!canCreate && !canUpdate" class="text-sm text-muted-foreground">{{ legalLabel(form.legal_type) }}</p>
    </form>
  </div>
</template>
