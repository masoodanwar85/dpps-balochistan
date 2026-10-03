<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
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

function levelTone(value) {
  return ({
    expired: 'bg-red-50 text-red-700 ring-red-200',
    red: 'bg-orange-50 text-orange-800 ring-orange-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    renewal: 'bg-sky-50 text-sky-800 ring-sky-200',
    document: 'bg-violet-50 text-violet-800 ring-violet-200',
  })[value] || 'bg-muted text-muted-foreground ring-border'
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
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="summary">
      <div class="grid gap-4 xl:grid-cols-3">
        <Card class="gap-0 overflow-hidden py-0 shadow-sm">
          <div class="h-1.5 bg-emerald-500" />
          <CardHeader class="px-5 pt-5 pb-0">
            <p class="text-xs font-semibold tracking-widest text-emerald-700 uppercase">Register</p>
            <h2 class="text-lg font-semibold">Companies</h2>
          </CardHeader>
          <CardContent class="grid grid-cols-2 gap-3 px-5 pt-4 pb-5">
            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-left transition hover:border-slate-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'companies' })">
              <span class="block text-xs font-medium text-slate-600">Total</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">{{ summary.companies.total }}</span>
            </button>
            <button type="button" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-left transition hover:border-emerald-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'companies', query: { status: 'active' } })">
              <span class="block text-xs font-medium text-emerald-800">Active</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-emerald-950 tabular-nums">{{ summary.companies.active }}</span>
            </button>
            <button type="button" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-left transition hover:border-amber-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'companies', query: { status: 'expiring' } })">
              <span class="block text-xs font-medium text-amber-800">Expiring</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-amber-950 tabular-nums">{{ summary.companies.expiring }}</span>
            </button>
            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-left transition hover:border-red-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'companies', query: { status: 'expired' } })">
              <span class="block text-xs font-medium text-red-800">Expired</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-red-950 tabular-nums">{{ summary.companies.expired }}</span>
            </button>
          </CardContent>
        </Card>

        <Card class="gap-0 overflow-hidden py-0 shadow-sm">
          <div class="h-1.5 bg-sky-500" />
          <CardHeader class="px-5 pt-5 pb-0">
            <p class="text-xs font-semibold tracking-widest text-sky-700 uppercase">Register</p>
            <h2 class="text-lg font-semibold">Dealers</h2>
          </CardHeader>
          <CardContent class="grid grid-cols-2 gap-3 px-5 pt-4 pb-5">
            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-left transition hover:border-slate-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'dealers' })">
              <span class="block text-xs font-medium text-slate-600">Total</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">{{ summary.dealers.total }}</span>
            </button>
            <button type="button" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-left transition hover:border-emerald-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'dealers', query: { status: 'active' } })">
              <span class="block text-xs font-medium text-emerald-800">Active</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-emerald-950 tabular-nums">{{ summary.dealers.active }}</span>
            </button>
            <button type="button" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-left transition hover:border-amber-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'dealers', query: { status: 'expiring' } })">
              <span class="block text-xs font-medium text-amber-800">Expiring</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-amber-950 tabular-nums">{{ summary.dealers.expiring }}</span>
            </button>
            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-left transition hover:border-red-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'dealers', query: { status: 'expired' } })">
              <span class="block text-xs font-medium text-red-800">Expired</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-red-950 tabular-nums">{{ summary.dealers.expired }}</span>
            </button>
          </CardContent>
        </Card>

        <Card class="gap-0 overflow-hidden py-0 shadow-sm">
          <div class="h-1.5 bg-violet-500" />
          <CardHeader class="px-5 pt-5 pb-0">
            <p class="text-xs font-semibold tracking-widest text-violet-700 uppercase">Issuance</p>
            <h2 class="text-lg font-semibold">Licenses</h2>
          </CardHeader>
          <CardContent class="grid grid-cols-2 gap-3 px-5 pt-4 pb-5">
            <button type="button" class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-3 text-left transition hover:border-violet-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'licenses', query: { kind: 'registration', year: String(summary.year) } })">
              <span class="block text-xs font-medium text-violet-800">New (this year)</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-violet-950 tabular-nums">{{ summary.licenses.new_this_year }}</span>
            </button>
            <button type="button" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3 text-left transition hover:border-sky-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'licenses', query: { kind: 'renewal', year: String(summary.year) } })">
              <span class="block text-xs font-medium text-sky-800">Renewed (this year)</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-sky-950 tabular-nums">{{ summary.licenses.renewed_this_year }}</span>
            </button>
            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-left transition hover:border-red-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'licenses', query: { status: 'expired' } })">
              <span class="block text-xs font-medium text-red-800">Expired</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-red-950 tabular-nums">{{ summary.licenses.expired }}</span>
            </button>
            <button type="button" class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-3 text-left transition hover:border-orange-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'licenses', query: { status: 'suspended' } })">
              <span class="block text-xs font-medium text-orange-800">Suspended</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-orange-950 tabular-nums">{{ summary.licenses.suspended }}</span>
            </button>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <Card class="gap-0 overflow-hidden py-0 shadow-sm">
          <div class="h-1.5 bg-amber-500" />
          <CardHeader class="px-5 pt-5 pb-0">
            <p class="text-xs font-semibold tracking-widest text-amber-700 uppercase">Workflow</p>
            <h2 class="text-lg font-semibold">Applications in process</h2>
          </CardHeader>
          <CardContent class="grid grid-cols-2 gap-3 px-5 pt-4 pb-5">
            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-left transition hover:border-slate-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'applications', query: { type: 'new', status: 'open' } })">
              <span class="block text-xs font-medium text-slate-600">New</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">{{ summary.applications.new }}</span>
            </button>
            <button type="button" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3 text-left transition hover:border-sky-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'applications', query: { type: 'renewal', status: 'open' } })">
              <span class="block text-xs font-medium text-sky-800">Renewal</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-sky-950 tabular-nums">{{ summary.applications.renewal }}</span>
            </button>
            <button type="button" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-left transition hover:border-amber-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'applications', query: { status: 'deficiency_issued' } })">
              <span class="block text-xs font-medium text-amber-800">Deficiency open</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-amber-950 tabular-nums">{{ summary.applications.deficiency_open }}</span>
            </button>
            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-left transition hover:border-red-300 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push({ name: 'applications', query: { status: 'open', sla: 'breached' } })">
              <span class="block text-xs font-medium text-red-800">SLA breached</span>
              <span class="mt-2 block text-3xl font-semibold tracking-tight text-red-700 tabular-nums">{{ summary.applications.sla_breached }}</span>
            </button>
          </CardContent>
        </Card>

        <Card class="h-full gap-0 overflow-hidden py-0 shadow-sm">
          <div class="h-1.5 bg-orange-500" />
          <CardHeader class="flex flex-row items-start justify-between gap-3 px-5 pt-5 pb-0">
            <div>
              <p class="text-xs font-semibold tracking-widest text-orange-700 uppercase">Queue</p>
              <h2 class="text-lg font-semibold">Pending verification</h2>
            </div>
            <Button type="button" variant="outline" size="sm" @click="router.push({ name: 'verification' })">Open Queue</Button>
          </CardHeader>
          <CardContent class="grid flex-1 grid-cols-1 gap-3 px-5 pt-4 pb-5 sm:grid-cols-3">
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-3">
              <p class="text-xs font-medium text-orange-800">Staff</p>
              <p class="mt-2 text-3xl font-semibold tracking-tight text-orange-950 tabular-nums">{{ summary.verification.staff }}</p>
            </div>
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-3">
              <p class="text-xs font-medium text-orange-800">Documents</p>
              <p class="mt-2 text-3xl font-semibold tracking-tight text-orange-950 tabular-nums">{{ summary.verification.documents }}</p>
            </div>
            <div class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-3">
              <p class="text-xs font-medium text-orange-800">Products</p>
              <p class="mt-2 text-3xl font-semibold tracking-tight text-orange-950 tabular-nums">{{ summary.verification.products }}</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card class="gap-0 overflow-hidden py-0 shadow-sm">
        <div class="h-1.5 bg-rose-500" />
        <CardHeader class="flex flex-col gap-3 px-5 pt-5 pb-0 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-semibold tracking-widest text-rose-700 uppercase">Compliance</p>
            <h2 class="text-lg font-semibold">Documents incomplete (current license period)</h2>
          </div>
          <Button type="button" variant="outline" size="sm" @click="router.push({ name: 'documents-incomplete' })">View list</Button>
        </CardHeader>
        <CardContent class="grid gap-3 px-5 pt-4 pb-5 sm:grid-cols-2">
          <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-xs font-medium text-rose-800">Companies</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-rose-950 tabular-nums">{{ summary.documents_incomplete.companies }}</p>
          </div>
          <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-xs font-medium text-rose-800">Dealers</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight text-rose-950 tabular-nums">{{ summary.documents_incomplete.dealers }}</p>
          </div>
        </CardContent>
      </Card>

      <Card class="gap-0 overflow-hidden py-0 shadow-sm">
        <div class="h-1.5 bg-slate-400" />
        <CardHeader class="px-5 pt-5 pb-0">
          <p class="text-xs font-semibold tracking-widest text-slate-500 uppercase">Find a record</p>
          <h2 class="text-lg font-semibold">Quick search</h2>
        </CardHeader>
        <CardContent class="px-5 pt-4 pb-5">
          <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
            <div class="grid gap-1.5">
              <Label for="dash-type">Search in</Label>
              <select id="dash-type" v-model="searchType" class="border-input h-9 min-w-40 rounded-md border bg-background px-2 text-sm shadow-xs" aria-label="Quick search">
                <option value="company">Company</option>
                <option value="dealer">Dealer</option>
                <option value="license">License</option>
                <option value="cnic">CNIC</option>
                <option value="mobile">Mobile</option>
                <option value="district">District</option>
              </select>
            </div>
            <div class="grid min-w-56 flex-1 gap-1.5">
              <Label for="dash-query">Text</Label>
              <Input id="dash-query" v-model="searchText" aria-label="Search text" />
            </div>
            <Button type="submit">Go</Button>
          </form>
          <ul v-if="results.length" class="mt-4 divide-y overflow-hidden rounded-lg border">
            <li v-for="row in results" :key="`${row.type}-${row.id}`">
              <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none" @click="router.push(row.route)">
                <span class="font-medium">{{ row.label }}</span>
                <span class="text-xs tracking-wide text-muted-foreground uppercase">{{ row.type }}</span>
              </button>
            </li>
          </ul>
        </CardContent>
      </Card>

      <Card class="gap-0 overflow-hidden py-0 shadow-sm">
        <div class="h-1.5 bg-red-500" />
        <CardHeader class="px-5 pt-5 pb-0">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p class="text-xs font-semibold tracking-widest text-red-700 uppercase">Watch list</p>
              <h2 class="text-lg font-semibold">Expiry alerts</h2>
            </div>
            <div class="flex flex-wrap items-end gap-2">
              <Button type="button" size="sm" :variant="party === 'company' ? 'default' : 'outline'" @click="party = 'company'; loadAlerts()">Companies</Button>
              <Button type="button" size="sm" :variant="party === 'dealer' ? 'default' : 'outline'" @click="party = 'dealer'; loadAlerts()">Dealers</Button>
              <div v-if="party === 'dealer'" class="grid gap-1">
                <Label for="dash-district">District</Label>
                <select id="dash-district" v-model="districtId" class="border-input h-8 rounded-md border bg-background px-2 text-sm shadow-xs" aria-label="District" @change="loadAlerts()">
                  <option value="">All</option>
                  <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
                </select>
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent class="grid gap-4 px-5 pt-4 pb-5">
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-sm text-red-800 ring-1 ring-red-200">
              <span class="size-2 rounded-full bg-red-500" />
              Expired
              <span class="font-semibold tabular-nums">{{ summary.alerts.expired }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1 text-sm text-orange-800 ring-1 ring-orange-200">
              <span class="size-2 rounded-full bg-orange-500" />
              Under {{ summary.alert_red_days }} days
              <span class="font-semibold tabular-nums">{{ summary.alerts.under_red }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-sm text-amber-900 ring-1 ring-amber-200">
              <span class="size-2 rounded-full bg-amber-400" />
              Under {{ summary.alert_amber_days }} days
              <span class="font-semibold tabular-nums">{{ summary.alerts.under_amber }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-sm text-sky-800 ring-1 ring-sky-200">
              <span class="size-2 rounded-full bg-sky-500" />
              Renewal window open, not applied
              <span class="font-semibold tabular-nums">{{ summary.alerts.renewal_window }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-sm text-violet-800 ring-1 ring-violet-200">
              <span class="size-2 rounded-full bg-violet-500" />
              Document expiring
              <span class="font-semibold tabular-nums">{{ summary.alerts.document_expiring }}</span>
            </span>
          </div>
          <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
              <thead class="bg-muted/70 text-left text-xs tracking-wide text-muted-foreground uppercase">
                <tr>
                  <th class="px-3 py-2.5 font-medium">Name</th>
                  <th class="px-3 py-2.5 font-medium">License No</th>
                  <th class="px-3 py-2.5 font-medium">Expiry</th>
                  <th class="px-3 py-2.5 font-medium">Days</th>
                  <th class="px-3 py-2.5 font-medium">Level</th>
                  <th class="px-3 py-2.5 font-medium"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in alerts" :key="row.id" class="border-t transition hover:bg-muted/40">
                  <td class="px-3 py-2.5 font-medium">{{ row.name }}</td>
                  <td class="px-3 py-2.5">{{ row.license_no }}</td>
                  <td class="px-3 py-2.5 tabular-nums">{{ displayDate(row.valid_to) }}</td>
                  <td class="px-3 py-2.5 tabular-nums">{{ row.days }}</td>
                  <td class="px-3 py-2.5">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="levelTone(row.level)">{{ levelLabel(row.level) }}</span>
                  </td>
                  <td class="px-3 py-2.5 text-right"><Button type="button" variant="outline" size="sm" @click="openAlert(row)">View</Button></td>
                </tr>
                <tr v-if="alerts.length === 0">
                  <td class="text-muted-foreground px-3 py-6 text-center" colspan="6">No alerts.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
