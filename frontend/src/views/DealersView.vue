<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, ensureCsrf, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const canCreate = computed(() => auth.can('dealers.create'))
const canUpdate = computed(() => auth.can('dealers.update'))
const canDelete = computed(() => auth.can('dealers.delete'))
const canExport = computed(() => auth.can('exports.run'))

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
const districts = ref([])
const search = ref('')
const districtId = ref('')
const tehsilId = ref('')
const status = ref('')
const page = ref(1)
const loadError = ref('')
const mode = ref('list')
const dealer = ref(null)
const form = ref(null)
const formError = ref('')
const warnings = ref([])
const warningReason = ref('')
const deleteReason = ref('')
const notice = ref('')
const saving = ref(false)

const showing = computed(() => {
  if (!meta.value.total) {
    return 'Showing 0 of 0'
  }

  const from = (meta.value.current_page - 1) * meta.value.per_page + 1
  const to = Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)

  return `Showing ${from}–${to} of ${meta.value.total}`
})

const tehsilChoices = computed(() => {
  const district = districts.value.find((item) => String(item.id) === String(districtId.value))

  return district?.tehsils ?? []
})

const formTehsils = computed(() => {
  const district = districts.value.find((item) => String(item.id) === String(form.value?.district_id))

  return district?.tehsils ?? []
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

function blankForm() {
  return {
    shop_name: '',
    district_id: '',
    tehsil_id: '',
    business_address: '',
    gps_lat: '',
    gps_lng: '',
    mobile: '',
    email: '',
  }
}

function queryString() {
  const params = new URLSearchParams()
  params.set('page', String(page.value))
  params.set('per_page', '25')

  if (search.value.trim()) {
    params.set('search', search.value.trim())
  }

  if (districtId.value) {
    params.set('filter[district_id]', districtId.value)
  }

  if (tehsilId.value) {
    params.set('filter[tehsil_id]', tehsilId.value)
  }

  if (status.value) {
    params.set('filter[status]', status.value)
  }

  return params.toString()
}

async function load() {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/dealers?${queryString()}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load dealers.'

    return
  }

  rows.value = payload.data
  meta.value = payload.meta
  districts.value = payload.meta.districts ?? districts.value
}

function applyFilters() {
  page.value = 1
  load()
}

function startCreate() {
  dealer.value = null
  form.value = blankForm()
  warnings.value = []
  warningReason.value = ''
  deleteReason.value = ''
  formError.value = ''
  notice.value = ''
  mode.value = 'form'
}

function openDealer(row) {
  router.push(`/dealers/${row.id}`)
}

async function openEditor(id) {
  formError.value = ''
  notice.value = ''
  const { response, payload } = await api(`/api/v1/dealers/${id}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this dealer.'

    return
  }

  const row = payload.data.dealer
  dealer.value = row
  form.value = {
    shop_name: row.shop_name,
    district_id: row.district_id,
    tehsil_id: row.tehsil_id || '',
    business_address: row.business_address,
    gps_lat: row.gps_lat ?? '',
    gps_lng: row.gps_lng ?? '',
    mobile: row.mobile || '',
    email: row.email || '',
  }
  warnings.value = []
  warningReason.value = ''
  deleteReason.value = ''
  mode.value = 'form'
}

function payloadBody(confirm) {
  const body = {
    shop_name: form.value.shop_name,
    district_id: Number(form.value.district_id),
    tehsil_id: form.value.tehsil_id ? Number(form.value.tehsil_id) : null,
    business_address: form.value.business_address,
    gps_lat: form.value.gps_lat === '' ? null : Number(form.value.gps_lat),
    gps_lng: form.value.gps_lng === '' ? null : Number(form.value.gps_lng),
    mobile: form.value.mobile || null,
    email: form.value.email || null,
  }

  if (confirm) {
    body.confirm_warnings = true
    body.warning_reason = warningReason.value
  }

  return body
}

async function save(confirm = false) {
  formError.value = ''

  if (!confirm) {
    warnings.value = []
  }

  saving.value = true
  const path = dealer.value ? `/api/v1/dealers/${dealer.value.id}` : '/api/v1/dealers'
  const { response, payload } = await api(path, {
    method: dealer.value ? 'PUT' : 'POST',
    body: payloadBody(confirm),
  })
  saving.value = false

  if (response.status === 409) {
    warnings.value = payload?.warnings ?? []

    return
  }

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this dealer.'

    return
  }

  notice.value = ''
  await router.push(`/dealers/${payload.data.id}`)
}

async function removeDealer() {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/dealers/${dealer.value.id}`, {
    method: 'DELETE',
    body: { reason: deleteReason.value },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not delete this dealer.'

    return
  }

  mode.value = 'list'
  await router.replace({ name: 'dealers' })
  await load()
}

async function exportExcel() {
  loadError.value = ''
  await ensureCsrf()
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/exports/dealers?${queryString()}`, {
    credentials: 'include',
  })

  if (!response.ok) {
    loadError.value = 'Could not export dealers.'

    return
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'dealers.xlsx'
  link.click()
  URL.revokeObjectURL(url)
}

function applyListQuery() {
  status.value = typeof route.query.status === 'string' ? route.query.status : ''
  districtId.value = typeof route.query.district === 'string' ? route.query.district : ''
}

watch(() => [route.query.status, route.query.district], () => {
  applyListQuery()
  page.value = 1
  load()
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
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="mode === 'list'">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-2">
          <div class="grid gap-1">
            <Label for="dealer-search">Search</Label>
            <Input id="dealer-search" v-model="search" class="w-56" placeholder="Shop name or code" aria-label="Search" @keyup.enter="applyFilters" />
          </div>
          <div class="grid gap-1">
            <Label for="dealer-district">District</Label>
            <select id="dealer-district" v-model="districtId" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="District" @change="tehsilId = ''">
              <option value="">All</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="dealer-tehsil">Tehsil</Label>
            <select id="dealer-tehsil" v-model="tehsilId" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Tehsil">
              <option value="">All</option>
              <option v-for="tehsil in tehsilChoices" :key="tehsil.id" :value="tehsil.id">{{ tehsil.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="dealer-status">Status</Label>
            <select id="dealer-status" v-model="status" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Status">
              <option v-for="[value, label] in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <Button type="button" variant="outline" @click="applyFilters">Search</Button>
        </div>
        <div class="flex gap-2">
          <Button v-if="canExport" type="button" variant="outline" @click="exportExcel">Export Excel</Button>
          <Button v-if="canCreate" type="button" @click="startCreate">New Dealer</Button>
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border bg-card">
        <table class="w-full text-left text-sm">
          <thead class="border-b text-muted-foreground">
            <tr>
              <th class="px-3 py-2 font-medium">Code</th>
              <th class="px-3 py-2 font-medium">Shop Name</th>
              <th class="px-3 py-2 font-medium">District</th>
              <th class="px-3 py-2 font-medium">Expiry</th>
              <th class="px-3 py-2 font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td class="px-3 py-3 text-muted-foreground" colspan="5">No dealers.</td>
            </tr>
            <tr v-for="row in rows" :key="row.id" class="cursor-pointer border-b last:border-0" @click="openDealer(row)">
              <td class="px-3 py-2">{{ row.dealer_code }}</td>
              <td class="px-3 py-2">{{ row.shop_name }}</td>
              <td class="px-3 py-2">{{ row.district_name }}</td>
              <td class="px-3 py-2">{{ displayDate(row.expiry_date) }}</td>
              <td class="px-3 py-2">{{ statusLabel(row.status) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex items-center justify-between text-sm">
        <span class="text-muted-foreground">{{ showing }}</span>
        <div class="flex gap-2">
          <Button type="button" variant="outline" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
          <Button type="button" variant="outline" :disabled="page >= meta.last_page" @click="page += 1; load()">Next</Button>
        </div>
      </div>
    </template>

    <form v-else class="grid max-w-xl gap-3" @submit.prevent="save(false)">
      <h2 class="text-lg font-semibold">{{ dealer ? dealer.shop_name : 'New dealer' }}</h2>
      <Alert v-if="formError" variant="destructive">
        <AlertTitle>{{ formError }}</AlertTitle>
      </Alert>
      <div class="grid gap-1">
        <Label for="shop-name">Shop name</Label>
        <Input id="shop-name" v-model="form.shop_name" required aria-label="Shop name" />
      </div>
      <div class="grid gap-1">
        <Label for="form-district">District</Label>
        <select id="form-district" v-model="form.district_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" required aria-label="District" @change="form.tehsil_id = ''">
          <option value="">Choose</option>
          <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
        </select>
      </div>
      <div class="grid gap-1">
        <Label for="form-tehsil">Tehsil</Label>
        <select id="form-tehsil" v-model="form.tehsil_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Tehsil">
          <option value="">None</option>
          <option v-for="tehsil in formTehsils" :key="tehsil.id" :value="tehsil.id">{{ tehsil.name }}</option>
        </select>
      </div>
      <div class="grid gap-1">
        <Label for="shop-address">Business address</Label>
        <Input id="shop-address" v-model="form.business_address" required aria-label="Business address" />
      </div>
      <div class="grid gap-1">
        <Label for="shop-mobile">Mobile</Label>
        <Input id="shop-mobile" v-model="form.mobile" aria-label="Mobile" />
      </div>
      <div class="grid gap-1">
        <Label for="shop-email">Email</Label>
        <Input id="shop-email" v-model="form.email" type="email" aria-label="Email" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="grid gap-1">
          <Label for="shop-lat">GPS latitude</Label>
          <Input id="shop-lat" v-model="form.gps_lat" aria-label="GPS latitude" />
        </div>
        <div class="grid gap-1">
          <Label for="shop-lng">GPS longitude</Label>
          <Input id="shop-lng" v-model="form.gps_lng" aria-label="GPS longitude" />
        </div>
      </div>
      <Alert v-for="warning in warnings" :key="warning">
        <AlertTitle>{{ warning }}</AlertTitle>
      </Alert>
      <div v-if="warnings.length" class="grid gap-1">
        <Label for="dealer-reason">Reason</Label>
        <Input id="dealer-reason" v-model="warningReason" aria-label="Warning reason" />
      </div>
      <div v-if="dealer && canDelete" class="grid gap-1">
        <Label for="dealer-delete-reason">Delete reason</Label>
        <Input id="dealer-delete-reason" v-model="deleteReason" aria-label="Delete reason" />
      </div>
      <div class="flex gap-2">
        <Button type="button" variant="outline" @click="mode = 'list'; load()">Cancel</Button>
        <Button v-if="warnings.length" type="button" :disabled="saving" @click="save(true)">Confirm and save</Button>
        <Button v-else type="submit" :disabled="saving || (dealer && !canUpdate)">Save</Button>
        <Button v-if="dealer && canDelete" type="button" variant="outline" :disabled="saving" @click="removeDealer">Delete</Button>
      </div>
    </form>
  </div>
</template>
