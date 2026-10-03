<script setup>
import { computed, onMounted, ref } from 'vue'
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
const canUpdate = computed(() => auth.can('dealers.update'))
const canIssue = computed(() => auth.can('licenses.issue'))
const canApply = computed(() => auth.can('applications.create'))
const canUpload = computed(() => auth.can('documents.upload'))
const canDownload = computed(() => auth.can('documents.download'))
const canVerifyDocument = computed(() => auth.can('documents.verify'))

const tabs = [
  ['overview', 'Overview'],
  ['owners', 'Owners'],
  ['licenses', 'Licenses'],
  ['applications', 'Applications'],
  ['documents', 'Documents'],
  ['activity', 'Activity'],
]
const laterTabs = ['applications']
const documentCategories = [
  ['', 'All'],
  ['application', 'Application'],
  ['cnic', 'CNIC'],
  ['license', 'License'],
  ['challan', 'Challan'],
  ['inspection', 'Inspection'],
  ['correspondence', 'Correspondence'],
  ['agreement', 'Agreement'],
  ['qualification', 'Qualification'],
  ['other', 'Other'],
]
const attestedBy = [
  ['none', 'None'],
  ['gazetted_officer', 'Gazetted officer'],
  ['notary_public', 'Notary public'],
  ['oath_commissioner', 'Oath commissioner'],
]
const statuses = {
  unlicensed: 'Unlicensed',
  active: 'Active',
  expiring: 'Expiring',
  expired: 'Expired',
  suspended: 'Suspended',
  cancelled: 'Cancelled',
}

const tab = ref('overview')
const profile = ref(null)
const owners = ref([])
const activity = ref([])
const documents = ref([])
const documentTypes = ref([])
const documentCategory = ref('')
const documentForm = ref(null)
const historyRows = ref([])
const loadError = ref('')
const notice = ref('')
const formError = ref('')
const saving = ref(false)
const panel = ref('')
const ownerForm = ref(null)
const ownerFound = ref(false)
const ownerChecked = ref(false)
const blocks = ref([])
const otherShops = ref([])
const warnings = ref([])
const warningReason = ref('')
const endForm = ref(null)
const licenseRows = ref([])
const licenseError = ref('')
const showSuspend = ref(false)
const suspendForm = ref({ reason: '', order_no: '', effective_date: '' })

const dealerId = computed(() => route.params.id)
const canSuspendLicense = computed(() => auth.can('licenses.suspend') && ['active', 'expired'].includes(profile.value?.license?.status))
const dealer = computed(() => profile.value?.dealer)

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).split('-')

  return `${day}-${month}-${year}`
}

function statusLabel(value) {
  return statuses[value] || value
}

function verificationLabel(value) {
  if (value === 'verified') {
    return 'Verified'
  }

  if (value === 'rejected') {
    return 'Rejected'
  }

  return 'Pending'
}

function viaLabel(value) {
  if (value === 'portal') {
    return 'Portal'
  }

  if (value === 'import') {
    return 'Import'
  }

  return 'Office'
}

async function loadProfile() {
  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this dealer.'

    return
  }

  profile.value = payload.data
}

async function loadOwners() {
  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}/owners`)

  if (response.ok) {
    owners.value = payload.data
  }
}

async function loadActivity() {
  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}/activity`)

  if (response.ok) {
    activity.value = payload.data
  }
}

async function loadDocuments() {
  const query = documentCategory.value ? `?category=${encodeURIComponent(documentCategory.value)}` : ''
  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}/documents${query}`)

  if (response.ok) {
    documents.value = payload.data
    documentTypes.value = payload.meta?.document_types ?? documentTypes.value
  }
}

function startOwner() {
  panel.value = 'owner'
  ownerForm.value = {
    cnic: '',
    full_name: '',
    father_name: '',
    mobile: '',
    email: '',
    start_date: '',
  }
  ownerFound.value = false
  ownerChecked.value = false
  blocks.value = []
  otherShops.value = []
  warnings.value = []
  warningReason.value = ''
  formError.value = ''
}

async function checkOwner() {
  formError.value = ''
  blocks.value = []
  otherShops.value = []
  ownerFound.value = false
  ownerChecked.value = false
  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}/owners/check`, {
    method: 'POST',
    body: { cnic: ownerForm.value.cnic },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not check this CNIC.'

    return
  }

  ownerChecked.value = true
  ownerFound.value = payload.data.found
  blocks.value = payload.data.blocks ?? []
  otherShops.value = payload.data.other_shops ?? []

  if (payload.data.person) {
    ownerForm.value.full_name = payload.data.person.full_name || ''
    ownerForm.value.father_name = payload.data.person.father_name || ''
    ownerForm.value.mobile = payload.data.person.mobile || ''
    ownerForm.value.email = payload.data.person.email || ''
  }
}

async function saveOwner(confirm = false) {
  formError.value = ''

  if (!confirm) {
    warnings.value = []
  }

  saving.value = true
  const body = {
    cnic: ownerForm.value.cnic,
    full_name: ownerForm.value.full_name,
    father_name: ownerForm.value.father_name || null,
    mobile: ownerForm.value.mobile,
    email: ownerForm.value.email || null,
    start_date: ownerForm.value.start_date,
  }

  if (confirm) {
    body.confirm_warnings = true
    body.warning_reason = warningReason.value
  }

  const { response, payload } = await api(`/api/v1/dealers/${dealerId.value}/owners`, {
    method: 'POST',
    body,
  })
  saving.value = false

  if (response.status === 409) {
    warnings.value = payload?.warnings ?? []

    return
  }

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not add this owner.'

    return
  }

  panel.value = ''
  notice.value = 'Owner added.'
  await Promise.all([loadOwners(), loadActivity()])
}

function startEnd(row) {
  panel.value = 'end'
  endForm.value = {
    id: row.id,
    name: row.full_name,
    end_date: '',
  }
  formError.value = ''
}

async function confirmEnd() {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/dealer-owners/${endForm.value.id}/end`, {
    method: 'POST',
    body: { end_date: endForm.value.end_date },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not end this ownership.'

    return
  }

  panel.value = ''
  notice.value = 'Ownership ended.'
  await Promise.all([loadOwners(), loadActivity()])
}

function startDocument(mode, row = null) {
  panel.value = 'document'
  documentForm.value = {
    mode,
    id: row?.id ?? null,
    document_type_id: row?.document_type_id ?? '',
    title: row?.title ?? '',
    issue_date: row?.issue_date ?? '',
    expiry_date: row?.expiry_date ?? '',
    attested_by: row?.attested_by ?? 'none',
    file: null,
  }
  historyRows.value = []
  warnings.value = []
  warningReason.value = ''
  formError.value = ''
}

function xsrfToken() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

  return match ? decodeURIComponent(match[1]) : ''
}

async function saveDocument(confirm = false) {
  formError.value = ''

  if (!confirm) {
    warnings.value = []
  }

  saving.value = true

  if (documentForm.value.mode === 'edit') {
    const { response, payload } = await api(`/api/v1/documents/${documentForm.value.id}`, {
      method: 'PUT',
      body: {
        document_type_id: Number(documentForm.value.document_type_id),
        title: documentForm.value.title,
        issue_date: documentForm.value.issue_date || null,
        expiry_date: documentForm.value.expiry_date || null,
        attested_by: documentForm.value.attested_by,
      },
    })
    saving.value = false

    if (!response.ok) {
      formError.value = firstError(payload?.errors) || 'Could not update this document.'

      return
    }

    panel.value = ''
    notice.value = 'Document updated.'
    await Promise.all([loadDocuments(), loadActivity()])

    return
  }

  if (!documentForm.value.file) {
    saving.value = false
    formError.value = 'Choose a file.'

    return
  }

  await ensureCsrf()
  const body = new FormData()
  body.append('file', documentForm.value.file)
  body.append('document_type_id', String(documentForm.value.document_type_id))
  body.append('title', documentForm.value.title)
  body.append('attested_by', documentForm.value.attested_by)

  if (documentForm.value.issue_date) {
    body.append('issue_date', documentForm.value.issue_date)
  }

  if (documentForm.value.expiry_date) {
    body.append('expiry_date', documentForm.value.expiry_date)
  }

  if (confirm) {
    body.append('confirm_warnings', '1')
    body.append('warning_reason', warningReason.value)
  }

  const path = documentForm.value.mode === 'replace'
    ? `/api/v1/documents/${documentForm.value.id}/replace`
    : `/api/v1/dealers/${dealerId.value}/documents`
  const response = await fetch(`${import.meta.env.VITE_API_URL}${path}`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': xsrfToken(),
    },
    body,
  })
  const payload = await response.json().catch(() => null)
  saving.value = false

  if (response.status === 409 && Array.isArray(payload?.warnings)) {
    warnings.value = payload.warnings

    return
  }

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not upload this document.'

    return
  }

  panel.value = ''
  warnings.value = []
  notice.value = documentForm.value.mode === 'replace' ? 'Document replaced.' : 'Document uploaded.'
  await Promise.all([loadDocuments(), loadActivity()])
}

async function viewDocument(row) {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/documents/${row.id}/download-url`)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not open this document.'

    return
  }

  window.open(payload.data.url, '_blank', 'noopener')
  await loadActivity()
}

async function showHistory(row) {
  const { response, payload } = await api(`/api/v1/documents/${row.id}/history`)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not load history.'

    return
  }

  historyRows.value = payload.data
}

async function verifyDocument(row) {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/documents/${row.id}/verify`, { method: 'POST', body: {} })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not verify this document.'

    return
  }

  notice.value = 'Document verified.'
  await Promise.all([loadDocuments(), loadActivity()])
}

async function loadLicenses() {
  licenseError.value = ''
  const params = new URLSearchParams({
    'filter[licensable_type]': 'dealer',
    'filter[licensable_id]': String(dealerId.value),
    per_page: '100',
  })
  const { response, payload } = await api(`/api/v1/licenses?${params}`)

  if (!response.ok) {
    licenseError.value = firstError(payload?.errors) || 'Licenses could not be loaded.'
    return
  }

  licenseRows.value = payload.data || []
}

function selectTab(key) {
  tab.value = key

  if (key === 'licenses') {
    loadLicenses()
  }
}

function startSuspend() {
  showSuspend.value = true
  suspendForm.value = { reason: '', order_no: '', effective_date: new Date().toISOString().slice(0, 10) }
}

async function saveSuspend() {
  const licenseId = profile.value?.license?.id

  if (!licenseId) {
    return
  }

  saving.value = true
  licenseError.value = ''
  const { response, payload } = await api(`/api/v1/licenses/${licenseId}/suspend`, {
    method: 'POST',
    body: suspendForm.value,
  })
  saving.value = false

  if (!response.ok) {
    licenseError.value = firstError(payload?.errors) || 'The license could not be suspended.'
    return
  }

  showSuspend.value = false
  notice.value = 'License suspended.'
  await loadProfile()
  await loadLicenses()
}

async function openCertificate() {
  const licenseId = profile.value?.license?.id

  if (!licenseId) {
    return
  }

  const { response, payload } = await api(`/api/v1/licenses/${licenseId}/certificate`)

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The certificate could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank')
}

onMounted(async () => {
  await loadProfile()

  if (profile.value) {
    await Promise.all([loadOwners(), loadDocuments(), loadActivity()])
  }
})
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="dealer">
      <PageSection accent="sky" eyebrow="Dealer profile" :title="`${dealer.shop_name} (${dealer.dealer_code})`">
        <template #actions>
          <Button v-if="canUpdate" type="button" variant="outline" size="sm" @click="router.push({ name: 'dealers', query: { edit: dealer.id } })">Edit</Button>
          <Button v-if="canApply" type="button" variant="outline" size="sm" @click="router.push({ name: 'applications', query: { new: 'dealer', id: dealer.id } })">New Application</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>New Application</Button>
          <Button v-if="canSuspendLicense" type="button" variant="outline" size="sm" @click="startSuspend">Suspend</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>Suspend</Button>
          <Button v-if="profile.license?.id" type="button" variant="outline" size="sm" @click="openCertificate">Certificate</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>Certificate</Button>
          <Button type="button" variant="outline" size="sm" @click="tab = 'activity'">Activity</Button>
          <Button type="button" variant="outline" size="sm" @click="router.push({ name: 'dealers' })">Back</Button>
        </template>
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
          <StatusBadge :value="dealer.status" :label="statusLabel(dealer.status)" />
          <span>{{ dealer.district_name }}<template v-if="dealer.tehsil_name"> › {{ dealer.tehsil_name }}</template></span>
          <template v-if="profile.license">
            <span>License {{ profile.license.license_no }}</span>
            <span>{{ displayDate(profile.license.valid_from) }} → {{ displayDate(profile.license.valid_to) }}</span>
          </template>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button
            v-for="[key, label] in tabs"
            :key="key"
            type="button"
            class="rounded-md px-3 py-1.5 text-sm ring-1 transition"
            :class="tab === key ? 'bg-sky-50 font-medium text-sky-900 ring-sky-200' : 'bg-background text-muted-foreground ring-border hover:bg-muted'"
            @click="selectTab(key)"
          >
            {{ label }}
          </button>
        </div>
      </PageSection>

      <Alert v-if="notice">
        <AlertTitle>{{ notice }}</AlertTitle>
      </Alert>
      <Alert v-if="formError" variant="destructive">
        <AlertTitle>{{ formError }}</AlertTitle>
      </Alert>

      <template v-if="tab === 'overview'">
        <div class="grid gap-6 lg:grid-cols-2">
          <PageSection accent="sky" eyebrow="Shop" title="Business details">
            <div class="grid gap-3">
              <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3">
                <p class="text-xs font-medium text-sky-800">Business address</p>
                <p class="mt-1 font-semibold text-sky-950">{{ dealer.business_address }}</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">District</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ dealer.district_name || '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">Tehsil</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ dealer.tehsil_name || '—' }}</p>
                </div>
              </div>
            </div>
          </PageSection>
          <PageSection accent="emerald" eyebrow="Contact" title="Reach this shop">
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                <p class="text-xs font-medium text-emerald-800">Mobile</p>
                <p class="mt-1 font-semibold text-emerald-950">{{ dealer.mobile || '—' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium text-slate-600">Email</p>
                <p class="mt-1 font-semibold text-slate-950 break-all">{{ dealer.email || '—' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 sm:col-span-2">
                <p class="text-xs font-medium text-slate-600">GPS</p>
                <p class="mt-1 font-semibold text-slate-950 tabular-nums">{{ dealer.gps_lat && dealer.gps_lng ? `${dealer.gps_lat}, ${dealer.gps_lng}` : '—' }}</p>
              </div>
            </div>
          </PageSection>
        </div>
        <PageSection v-if="profile.license" accent="violet" eyebrow="License" title="Current license">
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-3">
              <p class="text-xs font-medium text-violet-800">License no</p>
              <p class="mt-1 font-semibold text-violet-950">{{ profile.license.license_no }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
              <p class="text-xs font-medium text-slate-600">Valid from</p>
              <p class="mt-1 font-semibold text-slate-950 tabular-nums">{{ displayDate(profile.license.valid_from) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
              <p class="text-xs font-medium text-slate-600">Valid to</p>
              <p class="mt-1 font-semibold text-slate-950 tabular-nums">{{ displayDate(profile.license.valid_to) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
              <p class="text-xs font-medium text-slate-600">Status</p>
              <div class="mt-1"><StatusBadge :value="dealer.status" :label="statusLabel(dealer.status)" /></div>
            </div>
          </div>
        </PageSection>
      </template>

      <template v-else-if="tab === 'owners'">
      <PageSection accent="sky" eyebrow="People" title="Owners">
        <template #actions>
          <Button v-if="canUpdate" type="button" size="sm" @click="startOwner">Add owner</Button>
        </template>
      </PageSection>
      <PageSection v-if="panel === 'owner'" accent="violet" eyebrow="Owners" title="Add owner">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveOwner(false)">
          <div class="flex items-end gap-2">
            <div class="grid flex-1 gap-1">
              <Label for="owner-cnic">CNIC</Label>
              <Input id="owner-cnic" v-model="ownerForm.cnic" required aria-label="CNIC" />
            </div>
            <Button type="button" variant="outline" @click="checkOwner">Check</Button>
          </div>
          <p v-if="ownerChecked && ownerFound" class="text-sm">Found: {{ ownerForm.full_name }}</p>
          <p v-else-if="ownerChecked" class="text-sm">New person. Enter the name and mobile.</p>
          <p v-if="otherShops.length" class="text-sm">Other shops: {{ otherShops.join(', ') }}</p>
          <Alert v-for="block in blocks" :key="block.message" variant="destructive">
            <AlertTitle>{{ block.message }}</AlertTitle>
          </Alert>
          <template v-if="ownerChecked && !ownerFound">
            <div class="dpps-field">
              <Label for="owner-name">Name</Label>
              <Input id="owner-name" v-model="ownerForm.full_name" required aria-label="Name" />
            </div>
            <div class="dpps-field">
              <Label for="owner-father">Father name</Label>
              <Input id="owner-father" v-model="ownerForm.father_name" aria-label="Father name" />
            </div>
            <div class="dpps-field">
              <Label for="owner-mobile">Mobile</Label>
              <Input id="owner-mobile" v-model="ownerForm.mobile" required aria-label="Mobile" />
            </div>
          </template>
          <div class="dpps-field">
            <Label for="owner-start">Start date</Label>
            <Input id="owner-start" v-model="ownerForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="dpps-field">
            <Label for="owner-reason">Reason</Label>
            <Input id="owner-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="saveOwner(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !ownerChecked || blocks.length > 0">Save</Button>
          </div>
        </form>
      </PageSection>
      <PageSection v-if="panel === 'end'" accent="amber" eyebrow="Owners" :title="`End ownership — ${endForm.name}`">
        <form class="grid max-w-xl gap-3" @submit.prevent="confirmEnd">
          <div class="dpps-field">
            <Label for="owner-end">End date</Label>
            <Input id="owner-end" v-model="endForm.end_date" type="date" required aria-label="End date" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Confirm</Button>
          </div>
        </form>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>CNIC</th>
                <th>Mobile</th>
                <th>From</th>
                <th>To</th>
                <th>Other shops</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="owners.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No owners.</td>
              </tr>
              <tr v-for="row in owners" :key="row.id">
                <td>{{ row.full_name }}</td>
                <td>{{ row.cnic_display }}</td>
                <td>{{ row.mobile || '—' }}</td>
                <td>{{ displayDate(row.start_date) }}</td>
                <td>{{ displayDate(row.end_date) }}</td>
                <td>{{ row.other_shops.length ? row.other_shops.join(', ') : '—' }}</td>
                <td>
                  <Button v-if="canUpdate && !row.end_date" type="button" variant="outline" @click="startEnd(row)">End</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'documents'">
      <PageSection accent="rose" eyebrow="Compliance" title="Documents">
        <template #actions>
          <Button v-if="canUpload" type="button" size="sm" @click="startDocument('upload')">Upload</Button>
        </template>
        <div class="dpps-field max-w-xs">
          <Label for="document-category">Category</Label>
          <select id="document-category" v-model="documentCategory" class="dpps-select" aria-label="Category" @change="loadDocuments">
            <option v-for="[value, label] in documentCategories" :key="value" :value="value">{{ label }}</option>
          </select>
        </div>
      </PageSection>
      <PageSection v-if="panel === 'document'" accent="rose" eyebrow="Documents" :title="documentForm.mode === 'replace' ? 'Replace document' : documentForm.mode === 'edit' ? 'Edit document' : 'Upload document'">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveDocument(false)">
          <div v-if="documentForm.mode !== 'edit'" class="dpps-field">
            <Label for="document-file">File</Label>
            <input id="document-file" type="file" class="text-sm" aria-label="File" @change="documentForm.file = $event.target.files?.[0] || null">
            <p class="text-xs text-muted-foreground">PDF, JPG, or PNG.</p>
          </div>
          <div class="dpps-field">
            <Label for="document-type">Type</Label>
            <select id="document-type" v-model="documentForm.document_type_id" class="dpps-select" required aria-label="Type">
              <option value="">Choose</option>
              <option v-for="item in documentTypes" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="document-title">Title</Label>
            <Input id="document-title" v-model="documentForm.title" required aria-label="Title" />
          </div>
          <div class="dpps-field">
            <Label for="document-attested">Attested by</Label>
            <select id="document-attested" v-model="documentForm.attested_by" class="dpps-select" aria-label="Attested by">
              <option v-for="[value, label] in attestedBy" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="dpps-field">
            <Label for="document-reason">Reason</Label>
            <Input id="document-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving" @click="saveDocument(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Expiry</th>
                <th>Version</th>
                <th>Via</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="documents.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No documents.</td>
              </tr>
              <tr v-for="row in documents" :key="row.id">
                <td>{{ row.title }}</td>
                <td>{{ row.type_name }}</td>
                <td>{{ displayDate(row.expiry_date) }}</td>
                <td>{{ row.version_no }}</td>
                <td>{{ viaLabel(row.uploaded_via) }}</td>
                <td><StatusBadge :value="row.verification_status" :label="verificationLabel(row.verification_status)" /></td>
                <td>
                  <div class="flex flex-wrap gap-2">
                    <Button v-if="canDownload" type="button" variant="outline" @click="viewDocument(row)">View</Button>
                    <Button type="button" variant="outline" @click="showHistory(row)">History</Button>
                    <Button v-if="canUpload" type="button" variant="outline" @click="startDocument('replace', row)">Replace</Button>
                    <Button v-if="canVerifyDocument && row.verification_status === 'pending'" type="button" variant="outline" :disabled="saving" @click="verifyDocument(row)">Verify</Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      <PageSection v-if="historyRows.length" accent="slate" eyebrow="Documents" title="History" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <tbody>
              <tr v-for="row in historyRows" :key="row.id">
                <td>{{ row.version_no }}</td>
                <td>{{ row.title }}</td>
                <td><StatusBadge :value="row.verification_status" :label="verificationLabel(row.verification_status)" /></td>
                <td>{{ row.current ? 'Current' : 'Replaced' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'licenses'">
      <PageSection accent="violet" eyebrow="Licenses" title="License history">
        <template #actions>
          <Button v-if="canIssue" type="button" variant="outline" size="sm" @click="router.push({ name: 'previous-license', query: { type: 'dealer', id: route.params.id } })">Record previous license</Button>
        </template>
        <Alert v-if="licenseError" variant="destructive">
          <AlertTitle>{{ licenseError }}</AlertTitle>
        </Alert>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>License No</th>
                <th>Kind</th>
                <th>Valid from</th>
                <th>Valid to</th>
                <th>Status</th>
                <th>Documents</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="licenseRows.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="6">No licenses.</td>
              </tr>
              <tr v-for="row in licenseRows" :key="row.id">
                <td class="font-medium">{{ row.license_no }}</td>
                <td>{{ row.license_kind }}</td>
                <td>{{ displayDate(row.valid_from) }}</td>
                <td>{{ displayDate(row.valid_to) }}</td>
                <td><StatusBadge :value="row.status" :label="row.status" /></td>
                <td>{{ row.documents_status }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <PageSection v-else-if="laterTabs.includes(tab)" accent="slate" title="Not available yet">
        <p class="text-sm text-muted-foreground">This tab is not available yet.</p>
      </PageSection>

      <PageSection v-else-if="tab === 'activity'" accent="slate" eyebrow="Audit" title="Activity" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Action</th>
              <th>Description</th>
              <th>User</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="activity.length === 0">
              <td class="px-3 py-3 text-muted-foreground" colspan="4">No activity yet.</td>
            </tr>
            <tr v-for="row in activity" :key="row.id">
              <td>{{ row.created_at ? displayDate(row.created_at.slice(0, 10)) : '—' }}</td>
              <td>{{ row.action }}</td>
              <td>{{ row.description }}</td>
              <td>{{ row.user_name || '—' }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </PageSection>

      <PageSection v-if="showSuspend && profile.license" accent="rose" eyebrow="License" :title="`Suspend ${profile.license.license_no}`">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveSuspend">
        <Alert v-if="licenseError" variant="destructive">
          <AlertTitle>{{ licenseError }}</AlertTitle>
        </Alert>
        <div class="dpps-field">
          <Label for="dealer-suspend-reason">Reason</Label>
          <Input id="dealer-suspend-reason" v-model="suspendForm.reason" required minlength="3" aria-label="Reason" />
        </div>
        <div class="dpps-field">
          <Label for="dealer-suspend-order">Order number</Label>
          <Input id="dealer-suspend-order" v-model="suspendForm.order_no" required aria-label="Order number" />
        </div>
        <div class="dpps-field">
          <Label for="dealer-suspend-date">Effective date</Label>
          <Input id="dealer-suspend-date" v-model="suspendForm.effective_date" type="date" required aria-label="Effective date" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="showSuspend = false">Cancel</Button>
        </div>
      </form>
      </PageSection>
    </template>
  </div>
</template>
