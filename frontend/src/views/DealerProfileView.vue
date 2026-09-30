<script setup>
import { computed, onMounted, ref } from 'vue'
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
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="dealer">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">{{ dealer.shop_name }} ({{ dealer.dealer_code }})</h2>
          <p class="text-sm text-muted-foreground">
            {{ statusLabel(dealer.status) }}
            · {{ dealer.district_name }}<span v-if="dealer.tehsil_name"> › {{ dealer.tehsil_name }}</span>
            <span v-if="profile.license">
              · License {{ profile.license.license_no }}
              · {{ displayDate(profile.license.valid_from) }} → {{ displayDate(profile.license.valid_to) }}
            </span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canUpdate" type="button" variant="outline" @click="router.push({ name: 'dealers', query: { edit: dealer.id } })">Edit</Button>
          <Button v-if="canApply" type="button" variant="outline" @click="router.push({ name: 'applications', query: { new: 'dealer', id: dealer.id } })">New Application</Button>
          <Button v-else type="button" variant="outline" disabled>New Application</Button>
          <Button v-if="canSuspendLicense" type="button" variant="outline" @click="startSuspend">Suspend</Button>
          <Button v-else type="button" variant="outline" disabled>Suspend</Button>
          <Button v-if="profile.license?.id" type="button" variant="outline" @click="openCertificate">Certificate</Button>
          <Button v-else type="button" variant="outline" disabled>Certificate</Button>
          <Button type="button" variant="outline" @click="tab = 'activity'">Activity</Button>
          <Button type="button" variant="outline" @click="router.push({ name: 'dealers' })">Back</Button>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 border-b pb-2">
        <button
          v-for="[key, label] in tabs"
          :key="key"
          type="button"
          class="rounded-md px-3 py-1 text-sm"
          :class="tab === key ? 'bg-accent font-medium' : 'text-muted-foreground'"
          @click="selectTab(key)"
        >
          {{ label }}
        </button>
      </div>

      <Alert v-if="notice">
        <AlertTitle>{{ notice }}</AlertTitle>
      </Alert>
      <Alert v-if="formError" variant="destructive">
        <AlertTitle>{{ formError }}</AlertTitle>
      </Alert>

      <section v-if="tab === 'overview'" class="grid gap-2 text-sm">
        <p>{{ dealer.business_address }}</p>
        <p>Mobile {{ dealer.mobile || '—' }} · Email {{ dealer.email || '—' }}</p>
        <p>GPS {{ dealer.gps_lat && dealer.gps_lng ? `${dealer.gps_lat}, ${dealer.gps_lng}` : '—' }}</p>
      </section>

      <section v-else-if="tab === 'owners'" class="grid gap-3">
        <div v-if="canUpdate">
          <Button type="button" @click="startOwner">Add owner</Button>
        </div>
        <form v-if="panel === 'owner'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="saveOwner(false)">
          <h3 class="font-medium">Add owner</h3>
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
            <div class="grid gap-1">
              <Label for="owner-name">Name</Label>
              <Input id="owner-name" v-model="ownerForm.full_name" required aria-label="Name" />
            </div>
            <div class="grid gap-1">
              <Label for="owner-father">Father name</Label>
              <Input id="owner-father" v-model="ownerForm.father_name" aria-label="Father name" />
            </div>
            <div class="grid gap-1">
              <Label for="owner-mobile">Mobile</Label>
              <Input id="owner-mobile" v-model="ownerForm.mobile" required aria-label="Mobile" />
            </div>
          </template>
          <div class="grid gap-1">
            <Label for="owner-start">Start date</Label>
            <Input id="owner-start" v-model="ownerForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="grid gap-1">
            <Label for="owner-reason">Reason</Label>
            <Input id="owner-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="saveOwner(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !ownerChecked || blocks.length > 0">Save</Button>
          </div>
        </form>
        <form v-if="panel === 'end'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="confirmEnd">
          <h3 class="font-medium">End ownership — {{ endForm.name }}</h3>
          <div class="grid gap-1">
            <Label for="owner-end">End date</Label>
            <Input id="owner-end" v-model="endForm.end_date" type="date" required aria-label="End date" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Confirm</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Name</th>
                <th class="px-3 py-2 font-medium">CNIC</th>
                <th class="px-3 py-2 font-medium">Mobile</th>
                <th class="px-3 py-2 font-medium">From</th>
                <th class="px-3 py-2 font-medium">To</th>
                <th class="px-3 py-2 font-medium">Other shops</th>
                <th class="px-3 py-2 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="owners.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No owners.</td>
              </tr>
              <tr v-for="row in owners" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.full_name }}</td>
                <td class="px-3 py-2">{{ row.cnic_display }}</td>
                <td class="px-3 py-2">{{ row.mobile || '—' }}</td>
                <td class="px-3 py-2">{{ displayDate(row.start_date) }}</td>
                <td class="px-3 py-2">{{ displayDate(row.end_date) }}</td>
                <td class="px-3 py-2">{{ row.other_shops.length ? row.other_shops.join(', ') : '—' }}</td>
                <td class="px-3 py-2">
                  <Button v-if="canUpdate && !row.end_date" type="button" variant="outline" @click="startEnd(row)">End</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'documents'" class="grid gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div class="grid gap-1">
            <Label for="document-category">Category</Label>
            <select id="document-category" v-model="documentCategory" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Category" @change="loadDocuments">
              <option v-for="[value, label] in documentCategories" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <Button v-if="canUpload" type="button" @click="startDocument('upload')">Upload</Button>
        </div>
        <form v-if="panel === 'document'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="saveDocument(false)">
          <h3 class="font-medium">{{ documentForm.mode === 'replace' ? 'Replace document' : documentForm.mode === 'edit' ? 'Edit document' : 'Upload document' }}</h3>
          <div v-if="documentForm.mode !== 'edit'" class="grid gap-1">
            <Label for="document-file">File</Label>
            <input id="document-file" type="file" class="text-sm" aria-label="File" @change="documentForm.file = $event.target.files?.[0] || null">
            <p class="text-xs text-muted-foreground">PDF, JPG, or PNG.</p>
          </div>
          <div class="grid gap-1">
            <Label for="document-type">Type</Label>
            <select id="document-type" v-model="documentForm.document_type_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" required aria-label="Type">
              <option value="">Choose</option>
              <option v-for="item in documentTypes" :key="item.id" :value="item.id">{{ item.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="document-title">Title</Label>
            <Input id="document-title" v-model="documentForm.title" required aria-label="Title" />
          </div>
          <div class="grid gap-1">
            <Label for="document-attested">Attested by</Label>
            <select id="document-attested" v-model="documentForm.attested_by" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Attested by">
              <option v-for="[value, label] in attestedBy" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="grid gap-1">
            <Label for="document-reason">Reason</Label>
            <Input id="document-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving" @click="saveDocument(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Title</th>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium">Expiry</th>
                <th class="px-3 py-2 font-medium">Version</th>
                <th class="px-3 py-2 font-medium">Via</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="documents.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No documents.</td>
              </tr>
              <tr v-for="row in documents" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.title }}</td>
                <td class="px-3 py-2">{{ row.type_name }}</td>
                <td class="px-3 py-2">{{ displayDate(row.expiry_date) }}</td>
                <td class="px-3 py-2">{{ row.version_no }}</td>
                <td class="px-3 py-2">{{ viaLabel(row.uploaded_via) }}</td>
                <td class="px-3 py-2">{{ verificationLabel(row.verification_status) }}</td>
                <td class="px-3 py-2">
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
        <div v-if="historyRows.length" class="overflow-x-auto rounded-xl border bg-card">
          <h3 class="px-3 py-2 font-medium">History</h3>
          <table class="w-full text-left text-sm">
            <tbody>
              <tr v-for="row in historyRows" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.version_no }}</td>
                <td class="px-3 py-2">{{ row.title }}</td>
                <td class="px-3 py-2">{{ verificationLabel(row.verification_status) }}</td>
                <td class="px-3 py-2">{{ row.current ? 'Current' : 'Replaced' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'licenses'" class="grid gap-3">
        <div v-if="canIssue" class="flex justify-end">
          <Button type="button" variant="outline" @click="router.push({ name: 'previous-license', query: { type: 'dealer', id: route.params.id } })">Record previous license</Button>
        </div>
        <Alert v-if="licenseError" variant="destructive">
          <AlertTitle>{{ licenseError }}</AlertTitle>
        </Alert>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">License No</th>
                <th class="px-3 py-2 font-medium">Kind</th>
                <th class="px-3 py-2 font-medium">Valid from</th>
                <th class="px-3 py-2 font-medium">Valid to</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 font-medium">Documents</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="licenseRows.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="6">No licenses.</td>
              </tr>
              <tr v-for="row in licenseRows" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.license_no }}</td>
                <td class="px-3 py-2">{{ row.license_kind }}</td>
                <td class="px-3 py-2">{{ displayDate(row.valid_from) }}</td>
                <td class="px-3 py-2">{{ displayDate(row.valid_to) }}</td>
                <td class="px-3 py-2">{{ row.status }}</td>
                <td class="px-3 py-2">{{ row.documents_status }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="laterTabs.includes(tab)" class="text-sm text-muted-foreground">
        This tab is not available yet.
      </section>

      <section v-else-if="tab === 'activity'" class="overflow-x-auto rounded-xl border bg-card">
        <table class="w-full text-left text-sm">
          <thead class="border-b text-muted-foreground">
            <tr>
              <th class="px-3 py-2 font-medium">When</th>
              <th class="px-3 py-2 font-medium">Action</th>
              <th class="px-3 py-2 font-medium">Description</th>
              <th class="px-3 py-2 font-medium">User</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="activity.length === 0">
              <td class="px-3 py-3 text-muted-foreground" colspan="4">No activity yet.</td>
            </tr>
            <tr v-for="row in activity" :key="row.id" class="border-b last:border-0">
              <td class="px-3 py-2">{{ row.created_at ? displayDate(row.created_at.slice(0, 10)) : '—' }}</td>
              <td class="px-3 py-2">{{ row.action }}</td>
              <td class="px-3 py-2">{{ row.description }}</td>
              <td class="px-3 py-2">{{ row.user_name || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <form v-if="showSuspend && profile.license" class="grid max-w-xl gap-3 rounded-md border p-3" @submit.prevent="saveSuspend">
        <h3 class="font-medium">Suspend {{ profile.license.license_no }}</h3>
        <Alert v-if="licenseError" variant="destructive">
          <AlertTitle>{{ licenseError }}</AlertTitle>
        </Alert>
        <div class="grid gap-1">
          <Label for="dealer-suspend-reason">Reason</Label>
          <Input id="dealer-suspend-reason" v-model="suspendForm.reason" required minlength="3" aria-label="Reason" />
        </div>
        <div class="grid gap-1">
          <Label for="dealer-suspend-order">Order number</Label>
          <Input id="dealer-suspend-order" v-model="suspendForm.order_no" required aria-label="Order number" />
        </div>
        <div class="grid gap-1">
          <Label for="dealer-suspend-date">Effective date</Label>
          <Input id="dealer-suspend-date" v-model="suspendForm.effective_date" type="date" required aria-label="Effective date" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="showSuspend = false">Cancel</Button>
        </div>
      </form>
    </template>
  </div>
</template>
