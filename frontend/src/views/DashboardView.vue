<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const router = useRouter()
const summary = ref(null)
const alerts = ref([])
const districts = ref([])
const party = ref('company')
const districtId = ref('')
const searchType = ref('company')
const searchText = ref('')
const results = ref([])
const loadError = ref('')

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function levelLabel(value) {
  return ({
    expired: 'Expired',
    red: 'Under red alert',
    amber: 'Under amber alert',
    renewal: 'Renewal window',
    document: 'Document expiring',
  })[value] || value
}

async function loadSummary() {
  loadError.value = ''
  const { response, payload } = await api('/api/v1/dashboard/summary')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The dashboard could not be loaded.'
    return
  }

  summary.value = payload.data
}

async function loadAlerts() {
  const params = new URLSearchParams({ party: party.value })

  if (party.value === 'dealer' && districtId.value) {
    params.set('district_id', districtId.value)
  }

  const { response, payload } = await api(`/api/v1/dashboard/expiry-alerts?${params}`)
  alerts.value = response.ok ? payload.data || [] : []
}

async function loadDistricts() {
  const { response, payload } = await api('/api/v1/districts?per_page=100&sort=name')
  districts.value = response.ok ? payload.data || [] : []
}

async function search() {
  results.value = []

  if (searchText.value.trim().length < 2) {
    return
  }

  const params = new URLSearchParams({ type: searchType.value, q: searchText.value.trim() })
  const { response, payload } = await api(`/api/v1/search?${params}`)
  results.value = response.ok ? payload.data || [] : []
}

function openAlert(row) {
  const name = row.licensable_type === 'dealer' ? 'dealer-profile' : 'company-profile'
  router.push({ name, params: { id: row.licensable_id } })
}

onMounted(async () => {
  await Promise.all([loadSummary(), loadAlerts(), loadDistricts()])
})
</script>

<template>
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="summary">
      <div class="grid gap-3 md:grid-cols-3">
        <section class="rounded-md border p-3">
          <h2 class="font-medium">Companies</h2>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'companies' })">Total: {{ summary.companies.total }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'companies', query: { status: 'active' } })">Active: {{ summary.companies.active }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'companies', query: { status: 'expiring' } })">Expiring: {{ summary.companies.expiring }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'companies', query: { status: 'expired' } })">Expired: {{ summary.companies.expired }}</button>
        </section>
        <section class="rounded-md border p-3">
          <h2 class="font-medium">Dealers</h2>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'dealers' })">Total: {{ summary.dealers.total }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'dealers', query: { status: 'active' } })">Active: {{ summary.dealers.active }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'dealers', query: { status: 'expiring' } })">Expiring: {{ summary.dealers.expiring }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'dealers', query: { status: 'expired' } })">Expired: {{ summary.dealers.expired }}</button>
        </section>
        <section class="rounded-md border p-3">
          <h2 class="font-medium">Licenses</h2>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'licenses', query: { kind: 'registration', year: String(summary.year) } })">New (this year): {{ summary.licenses.new_this_year }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'licenses', query: { kind: 'renewal', year: String(summary.year) } })">Renewed (this year): {{ summary.licenses.renewed_this_year }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'licenses', query: { status: 'expired' } })">Expired: {{ summary.licenses.expired }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'licenses', query: { status: 'suspended' } })">Suspended: {{ summary.licenses.suspended }}</button>
        </section>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <section class="rounded-md border p-3">
          <h2 class="font-medium">Applications in process</h2>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'applications', query: { type: 'new', status: 'open' } })">New: {{ summary.applications.new }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'applications', query: { type: 'renewal', status: 'open' } })">Renewal: {{ summary.applications.renewal }}</button>
          <button type="button" class="block text-sm underline" @click="router.push({ name: 'applications', query: { status: 'deficiency_issued' } })">Deficiency open: {{ summary.applications.deficiency_open }}</button>
          <button type="button" class="text-sm text-red-700 underline" @click="router.push({ name: 'applications', query: { status: 'open', sla: 'breached' } })">SLA breached: {{ summary.applications.sla_breached }}</button>
        </section>
        <section class="rounded-md border p-3">
          <h2 class="font-medium">Pending verification</h2>
          <p class="text-sm">Staff: {{ summary.verification.staff }}</p>
          <p class="text-sm">Documents: {{ summary.verification.documents }}</p>
          <p class="text-sm">Products: {{ summary.verification.products }}</p>
          <Button type="button" variant="outline" class="mt-2" @click="router.push({ name: 'verification' })">Open Queue</Button>
        </section>
      </div>

      <section class="rounded-md border p-3">
        <h2 class="font-medium">Documents incomplete (current license period)</h2>
        <p class="text-sm">Companies: {{ summary.documents_incomplete.companies }} · Dealers: {{ summary.documents_incomplete.dealers }}</p>
        <Button type="button" variant="outline" class="mt-2" @click="router.push({ name: 'documents-incomplete' })">View list</Button>
      </section>

      <form class="flex flex-wrap items-end gap-2" @submit.prevent="search">
        <div class="grid gap-1">
          <Label for="dash-type">Quick search</Label>
          <select id="dash-type" v-model="searchType" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Quick search">
            <option value="company">Company</option>
            <option value="dealer">Dealer</option>
            <option value="license">License</option>
            <option value="cnic">CNIC</option>
            <option value="mobile">Mobile</option>
            <option value="district">District</option>
          </select>
        </div>
        <Input v-model="searchText" aria-label="Search text" class="max-w-xs" />
        <Button type="submit" variant="outline">Go</Button>
      </form>
      <ul v-if="results.length" class="text-sm">
        <li v-for="row in results" :key="`${row.type}-${row.id}`">
          <button type="button" class="underline" @click="router.push(row.route)">{{ row.label }}</button>
        </li>
      </ul>

      <section class="grid gap-2">
        <div class="flex flex-wrap items-end gap-2">
          <h2 class="font-medium">Expiry alerts</h2>
          <Button type="button" variant="outline" @click="party = 'company'; loadAlerts()">Companies</Button>
          <Button type="button" variant="outline" @click="party = 'dealer'; loadAlerts()">Dealers</Button>
          <div v-if="party === 'dealer'" class="grid gap-1">
            <Label for="dash-district">District</Label>
            <select id="dash-district" v-model="districtId" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="District" @change="loadAlerts()">
              <option value="">All</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
        </div>
        <p class="text-sm">
          Expired ({{ summary.alerts.expired }}) · Under {{ summary.alert_red_days }} days ({{ summary.alerts.under_red }}) · Under {{ summary.alert_amber_days }} days ({{ summary.alerts.under_amber }}) · Renewal window open, not applied ({{ summary.alerts.renewal_window }}) · Document expiring ({{ summary.alerts.document_expiring }})
        </p>
        <div class="overflow-x-auto rounded-md border">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
              <tr>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">License No</th>
                <th class="px-3 py-2">Expiry</th>
                <th class="px-3 py-2">Days</th>
                <th class="px-3 py-2">Level</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in alerts" :key="row.id" class="border-t">
                <td class="px-3 py-2">{{ row.name }}</td>
                <td class="px-3 py-2">{{ row.license_no }}</td>
                <td class="px-3 py-2">{{ displayDate(row.valid_to) }}</td>
                <td class="px-3 py-2">{{ row.days }}</td>
                <td class="px-3 py-2">{{ levelLabel(row.level) }}</td>
                <td class="px-3 py-2"><Button type="button" variant="outline" @click="openAlert(row)">View</Button></td>
              </tr>
              <tr v-if="alerts.length === 0">
                <td class="text-muted-foreground px-3 py-4" colspan="6">No alerts.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
