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
const canUpdate = computed(() => auth.can('companies.update'))
const canIssue = computed(() => auth.can('licenses.issue'))
const canApply = computed(() => auth.can('applications.create'))
const canStaff = computed(() => auth.can('staff.manage'))
const canVerify = computed(() => auth.can('staff.verify'))
const canProducts = computed(() => auth.can('products.manage'))
const canPersons = computed(() => auth.can('persons.view'))
const canUpload = computed(() => auth.can('documents.upload'))
const canDownload = computed(() => auth.can('documents.download'))
const canVerifyDocument = computed(() => auth.can('documents.verify'))

const tabs = [
  ['overview', 'Overview'],
  ['people', 'People'],
  ['staff', 'Tech Staff'],
  ['premises', 'Premises'],
  ['products', 'Products'],
  ['licenses', 'Licenses'],
  ['applications', 'Applications'],
  ['documents', 'Documents'],
  ['portal', 'Portal Users'],
  ['activity', 'Activity'],
]
const laterTabs = ['applications', 'portal']
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
const officerRoles = [
  ['ceo', 'CEO'],
  ['director', 'Director'],
  ['contact_person', 'Contact person'],
  ['authorized_rep', 'Authorized rep'],
]
const premiseTypes = [
  ['head_office', 'Head office'],
  ['regional_office', 'Regional office'],
  ['field_office', 'Field office'],
  ['warehouse', 'Warehouse'],
]
const statuses = {
  unlicensed: 'Unlicensed',
  active: 'Active',
  expiring: 'Expiring',
  expired: 'Expired',
  suspended: 'Suspended',
  cancelled: 'Cancelled',
}
const legalTypes = {
  private_ltd: 'Private Ltd',
  public_ltd: 'Public Ltd',
  partnership: 'Partnership',
  sole_proprietor: 'Sole proprietor',
  other: 'Other',
}

const tab = ref('overview')
const profile = ref(null)
const people = ref([])
const qualifications = ref([])
const premises = ref([])
const districts = ref([])
const assets = ref([])
const products = ref([])
const productChoices = ref([])
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
const personForm = ref(null)
const personFound = ref(false)
const personChecked = ref(false)
const blocks = ref([])
const warnings = ref([])
const warningReason = ref('')
const endForm = ref(null)
const premiseForm = ref(null)
const assetForm = ref(null)
const productForm = ref(null)
const deleteReason = ref('')
const staffFilter = ref('current')
const licenseRows = ref([])
const licenseError = ref('')
const showSuspend = ref(false)
const suspendForm = ref({ reason: '', order_no: '', effective_date: '' })

const companyId = computed(() => route.params.id)
const canSuspendLicense = computed(() => auth.can('licenses.suspend') && ['active', 'expired'].includes(profile.value?.license?.status))
const company = computed(() => profile.value?.company)
const officers = computed(() => people.value.filter((row) => row.role !== 'technical_staff'))
const staff = computed(() => people.value.filter((row) => row.role === 'technical_staff').filter((row) => {
  if (staffFilter.value === 'current') {
    return !row.end_date
  }

  if (staffFilter.value === 'ended') {
    return !!row.end_date
  }

  return true
}))

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).split('-')

  return `${day}-${month}-${year}`
}

function emptyToNull(value) {
  const text = typeof value === 'string' ? value.trim() : value

  return text === '' || text === undefined || text === null ? null : text
}

function statusLabel(value) {
  return statuses[value] || value
}

async function loadProfile() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/profile`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this company.'

    return
  }

  profile.value = payload.data
}

async function loadPeople() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/people`)

  if (!response.ok) {
    return
  }

  people.value = payload.data
  qualifications.value = payload.meta?.qualifications ?? []

  if (profile.value) {
    profile.value.verified_technical_staff = payload.meta.verified_technical_staff
    profile.value.minimum_technical_staff = payload.meta.minimum_technical_staff
  }
}

async function loadPremises() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/premises`)

  if (response.ok) {
    premises.value = payload.data
    districts.value = payload.meta?.districts ?? districts.value
  }
}

async function loadAssets() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/assets`)

  if (response.ok) {
    assets.value = payload.data
    districts.value = payload.meta?.districts ?? districts.value
  }
}

async function loadProducts() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/products`)

  if (response.ok) {
    products.value = payload.data
    productChoices.value = payload.meta?.products ?? []
  }
}

async function loadActivity() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/activity`)

  if (response.ok) {
    activity.value = payload.data
  }
}

function blankPerson(role) {
  return {
    role,
    cnic: '',
    full_name: '',
    father_name: '',
    mobile: '',
    email: '',
    qualification_id: '',
    institution: '',
    passing_year: '',
    designation: '',
    start_date: '',
  }
}

function startPerson(role) {
  panel.value = role === 'technical_staff' ? 'staff' : 'person'
  personForm.value = blankPerson(role)
  personFound.value = false
  personChecked.value = false
  blocks.value = []
  warnings.value = []
  warningReason.value = ''
  formError.value = ''
  endForm.value = null
}

async function checkPerson() {
  formError.value = ''
  blocks.value = []
  warnings.value = []
  personChecked.value = false
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/people/check`, {
    method: 'POST',
    body: {
      cnic: personForm.value.cnic,
      role: personForm.value.role,
    },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not check this CNIC.'

    return
  }

  personChecked.value = true
  personFound.value = payload.data.found
  blocks.value = payload.data.blocks ?? []

  if (payload.data.person) {
    personForm.value.full_name = payload.data.person.full_name
    personForm.value.father_name = payload.data.person.father_name || ''
    personForm.value.mobile = payload.data.person.mobile
    personForm.value.email = payload.data.person.email || ''
  }
}

async function savePerson(confirm = false) {
  formError.value = ''
  saving.value = true
  const body = {
    role: personForm.value.role,
    cnic: personForm.value.cnic,
    full_name: emptyToNull(personForm.value.full_name),
    father_name: emptyToNull(personForm.value.father_name),
    mobile: emptyToNull(personForm.value.mobile),
    email: emptyToNull(personForm.value.email),
    qualification_id: personForm.value.qualification_id ? Number(personForm.value.qualification_id) : null,
    institution: emptyToNull(personForm.value.institution),
    passing_year: personForm.value.passing_year ? Number(personForm.value.passing_year) : null,
    designation: emptyToNull(personForm.value.designation),
    start_date: personForm.value.start_date,
  }

  if (confirm) {
    body.confirm_warnings = true
    body.warning_reason = warningReason.value
  }

  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/people`, {
    method: 'POST',
    body,
  })
  saving.value = false

  if (response.status === 409) {
    warnings.value = payload?.warnings ?? []

    return
  }

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this person.'

    return
  }

  panel.value = ''
  notice.value = 'Person saved.'
  await Promise.all([loadPeople(), loadProfile(), loadActivity()])
}

function startEnd(row) {
  const verified = row.verification_status === 'verified' && row.role === 'technical_staff'
  const remaining = (profile.value?.verified_technical_staff ?? 0) - (verified ? 1 : 0)
  const minimum = profile.value?.minimum_technical_staff ?? 2
  endForm.value = {
    id: row.id,
    name: row.full_name,
    end_date: '',
    end_reason: '',
    note: remaining < minimum
      ? `Company will have ${remaining} verified staff left (minimum ${minimum}).`
      : '',
  }
  panel.value = 'end'
  formError.value = ''
}

async function confirmEnd() {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/company-people/${endForm.value.id}/end`, {
    method: 'POST',
    body: {
      end_date: endForm.value.end_date,
      end_reason: endForm.value.end_reason,
    },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not end this employment.'

    return
  }

  panel.value = ''
  notice.value = payload.data.staff_note || 'Employment ended.'
  await Promise.all([loadPeople(), loadProfile(), loadActivity()])
}

async function verifyStaff(row) {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/company-people/${row.id}/verify`, { method: 'POST', body: {} })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not verify this person.'

    return
  }

  notice.value = 'Technical staff verified.'
  await Promise.all([loadPeople(), loadProfile(), loadActivity()])
}

function viewPerson(row) {
  router.push({ name: 'persons', query: { cnic: row.cnic_display || row.cnic } })
}

function startPremise(row = null) {
  panel.value = 'premise'
  premiseForm.value = row
    ? {
        id: row.id,
        type: row.type,
        district_id: row.district_id || '',
        address: row.address,
        gps_lat: row.gps_lat ?? '',
        gps_lng: row.gps_lng ?? '',
        phone: row.phone || '',
        is_active: row.is_active,
      }
    : {
        id: null,
        type: 'warehouse',
        district_id: '',
        address: '',
        gps_lat: '',
        gps_lng: '',
        phone: '',
        is_active: true,
      }
  formError.value = ''
}

async function savePremise() {
  formError.value = ''
  saving.value = true
  const body = {
    type: premiseForm.value.type,
    district_id: premiseForm.value.district_id ? Number(premiseForm.value.district_id) : null,
    address: premiseForm.value.address,
    gps_lat: premiseForm.value.gps_lat === '' ? null : Number(premiseForm.value.gps_lat),
    gps_lng: premiseForm.value.gps_lng === '' ? null : Number(premiseForm.value.gps_lng),
    phone: emptyToNull(premiseForm.value.phone),
    is_active: premiseForm.value.is_active,
  }
  const path = premiseForm.value.id
    ? `/api/v1/company-premises/${premiseForm.value.id}`
    : `/api/v1/companies/${companyId.value}/premises`
  const { response, payload } = await api(path, {
    method: premiseForm.value.id ? 'PUT' : 'POST',
    body,
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this premise.'

    return
  }

  panel.value = ''
  notice.value = 'Premise saved.'
  await Promise.all([loadPremises(), loadActivity()])
}

function startAsset(row = null) {
  panel.value = 'asset'
  assetForm.value = row
    ? {
        id: row.id,
        asset_type: row.asset_type,
        description: row.description,
        district_id: row.district_id || '',
        estimated_value: row.estimated_value ?? '',
      }
    : {
        id: null,
        asset_type: 'movable',
        description: '',
        district_id: '',
        estimated_value: '',
      }
  formError.value = ''
}

async function saveAsset() {
  formError.value = ''
  saving.value = true
  const body = {
    asset_type: assetForm.value.asset_type,
    description: assetForm.value.description,
    district_id: assetForm.value.district_id ? Number(assetForm.value.district_id) : null,
    estimated_value: assetForm.value.estimated_value === '' ? null : Number(assetForm.value.estimated_value),
  }
  const path = assetForm.value.id
    ? `/api/v1/company-assets/${assetForm.value.id}`
    : `/api/v1/companies/${companyId.value}/assets`
  const { response, payload } = await api(path, {
    method: assetForm.value.id ? 'PUT' : 'POST',
    body,
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this asset.'

    return
  }

  panel.value = ''
  notice.value = 'Asset saved.'
  await Promise.all([loadAssets(), loadActivity()])
}

function startProduct(row = null) {
  panel.value = 'product'
  deleteReason.value = ''
  productForm.value = row
    ? {
        id: row.id,
        brand_name: row.brand_name,
        product_id: row.product_id || '',
        dpp_registration_no: row.dpp_registration_no || '',
        dpp_valid_to: row.dpp_valid_to || '',
        source: row.source,
        sample_provided: row.sample_provided,
        remarks: row.remarks || '',
      }
    : {
        id: null,
        brand_name: '',
        product_id: '',
        dpp_registration_no: '',
        dpp_valid_to: '',
        source: 'own_import',
        sample_provided: false,
        remarks: '',
      }
  formError.value = ''
}

async function saveProduct() {
  formError.value = ''
  saving.value = true
  const body = {
    brand_name: productForm.value.brand_name,
    product_id: Number(productForm.value.product_id),
    dpp_registration_no: emptyToNull(productForm.value.dpp_registration_no),
    dpp_valid_to: emptyToNull(productForm.value.dpp_valid_to),
    source: productForm.value.source,
    sample_provided: productForm.value.sample_provided,
    remarks: emptyToNull(productForm.value.remarks),
  }
  const path = productForm.value.id
    ? `/api/v1/company-products/${productForm.value.id}`
    : `/api/v1/companies/${companyId.value}/products`
  const { response, payload } = await api(path, {
    method: productForm.value.id ? 'PUT' : 'POST',
    body,
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this product.'

    return
  }

  panel.value = ''
  notice.value = 'Product saved.'
  await Promise.all([loadProducts(), loadActivity()])
}

async function removeProduct() {
  formError.value = ''
  saving.value = true
  const { response, payload } = await api(`/api/v1/company-products/${productForm.value.id}`, {
    method: 'DELETE',
    body: { reason: deleteReason.value },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not remove this product.'

    return
  }

  panel.value = ''
  notice.value = 'Product removed.'
  await Promise.all([loadProducts(), loadActivity()])
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

function blankDocument(mode, row = null) {
  return {
    mode,
    id: row?.id ?? null,
    document_type_id: row?.document_type_id ?? '',
    title: row?.title ?? '',
    issue_date: row?.issue_date ?? '',
    expiry_date: row?.expiry_date ?? '',
    attested_by: row?.attested_by ?? 'none',
    file: null,
  }
}

function startDocument(mode, row = null) {
  panel.value = 'document'
  documentForm.value = blankDocument(mode, row)
  historyRows.value = []
  warnings.value = []
  warningReason.value = ''
  formError.value = ''
}

async function loadDocuments() {
  const query = documentCategory.value ? `?category=${encodeURIComponent(documentCategory.value)}` : ''
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/documents${query}`)

  if (response.ok) {
    documents.value = payload.data
    documentTypes.value = payload.meta?.document_types ?? documentTypes.value
  }
}

function documentFields() {
  return {
    document_type_id: Number(documentForm.value.document_type_id),
    title: documentForm.value.title,
    issue_date: emptyToNull(documentForm.value.issue_date),
    expiry_date: emptyToNull(documentForm.value.expiry_date),
    attested_by: documentForm.value.attested_by,
  }
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
      body: documentFields(),
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
    : `/api/v1/companies/${companyId.value}/documents`
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
  formError.value = ''
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

function verificationLabel(value) {
  if (value === 'verified') {
    return 'Verified'
  }

  if (value === 'rejected') {
    return 'Rejected'
  }

  return 'Pending'
}

async function loadLicenses() {
  licenseError.value = ''
  const params = new URLSearchParams({
    'filter[licensable_type]': 'company',
    'filter[licensable_id]': String(companyId.value),
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
    await Promise.all([loadPeople(), loadPremises(), loadAssets(), loadProducts(), loadDocuments(), loadActivity()])
  }
})
</script>

<template>
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="company">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">{{ company.name }} ({{ company.company_code }})</h2>
          <p class="text-sm text-muted-foreground">
            {{ statusLabel(company.status) }}
            <span v-if="profile.license">
              · License {{ profile.license.license_no }}
              · {{ displayDate(profile.license.valid_from) }} → {{ displayDate(profile.license.valid_to) }}
              <span v-if="profile.license.days_remaining !== null">({{ profile.license.days_remaining }} d)</span>
            </span>
            <span v-if="profile.license?.documents_status === 'incomplete'"> · Docs incomplete</span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canUpdate" type="button" variant="outline" @click="router.push({ name: 'companies', query: { edit: company.id } })">Edit</Button>
          <Button v-if="canApply" type="button" variant="outline" @click="router.push({ name: 'applications', query: { new: 'company', id: company.id } })">New Application</Button>
          <Button v-else type="button" variant="outline" disabled>New Application</Button>
          <Button v-if="canSuspendLicense" type="button" variant="outline" @click="startSuspend">Suspend</Button>
          <Button v-else type="button" variant="outline" disabled>Suspend</Button>
          <Button v-if="profile.license?.id" type="button" variant="outline" @click="openCertificate">Certificate</Button>
          <Button v-else type="button" variant="outline" disabled>Certificate</Button>
          <Button type="button" variant="outline" @click="tab = 'activity'">Activity</Button>
          <Button type="button" variant="outline" @click="router.push({ name: 'companies' })">Back</Button>
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
      <form v-if="panel === 'end'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="confirmEnd">
        <h3 class="font-medium">End employment — {{ endForm.name }}</h3>
        <div class="grid gap-1">
          <Label for="end-date">End date</Label>
          <Input id="end-date" v-model="endForm.end_date" type="date" required aria-label="End date" />
        </div>
        <div class="grid gap-1">
          <Label for="end-reason">Reason</Label>
          <Input id="end-reason" v-model="endForm.end_reason" required aria-label="Reason" />
        </div>
        <Alert v-if="endForm.note">
          <AlertTitle>{{ endForm.note }}</AlertTitle>
        </Alert>
        <div class="flex gap-2">
          <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
          <Button type="submit" :disabled="saving">Confirm</Button>
        </div>
      </form>

      <section v-if="tab === 'overview'" class="grid gap-3 text-sm">
        <h3 class="font-medium">Company profile</h3>
        <p>{{ legalTypes[company.legal_type] || company.legal_type }} · NTN {{ company.ntn || '—' }}</p>
        <p>
          Incorporation {{ company.incorporation_no || '—' }}
          <span v-if="company.incorporation_date"> · {{ displayDate(company.incorporation_date) }}</span>
          · PCPA {{ company.pcpa_member ? 'Yes' : 'No' }}
          · CropLife {{ company.croplife_member ? 'Yes' : 'No' }}
        </p>
        <h3 class="font-medium">Contact</h3>
        <p>{{ company.head_office_address }}, {{ company.city }}<span v-if="company.province_name">, {{ company.province_name }}</span></p>
        <p>Landline {{ company.landline || '—' }} · Mobile {{ company.mobile || '—' }} · Email {{ company.email || '—' }}</p>
        <p>Contact person {{ profile.contacts.map((row) => row.name).join(', ') || '—' }}</p>
        <Alert v-for="alert in profile.alerts" :key="alert.kind">
          <AlertTitle>{{ alert.message }}</AlertTitle>
        </Alert>
      </section>

      <section v-else-if="tab === 'people'" class="grid gap-3">
        <div v-if="canStaff">
          <Button type="button" @click="startPerson('ceo')">Add person</Button>
        </div>
        <form v-if="panel === 'person'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="savePerson(false)">
          <h3 class="font-medium">Add person</h3>
          <div class="grid gap-1">
            <Label for="person-role">Role</Label>
            <select id="person-role" v-model="personForm.role" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Role">
              <option v-for="[value, label] in officerRoles" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <div class="grid flex-1 gap-1">
              <Label for="person-cnic">CNIC</Label>
              <Input id="person-cnic" v-model="personForm.cnic" required aria-label="CNIC" />
            </div>
            <Button type="button" variant="outline" @click="checkPerson">Check</Button>
          </div>
          <Alert v-for="block in blocks" :key="block.message" variant="destructive">
            <AlertTitle>{{ block.message }}</AlertTitle>
          </Alert>
          <p v-if="personChecked && personFound && blocks.length === 0" class="text-sm">Found: {{ personForm.full_name }}. Details pre-filled.</p>
          <template v-if="personChecked && !personFound">
            <div class="grid gap-1">
              <Label for="person-name">Name</Label>
              <Input id="person-name" v-model="personForm.full_name" required aria-label="Name" />
            </div>
            <div class="grid gap-1">
              <Label for="person-father">Father name</Label>
              <Input id="person-father" v-model="personForm.father_name" aria-label="Father name" />
            </div>
            <div class="grid gap-1">
              <Label for="person-mobile">Mobile</Label>
              <Input id="person-mobile" v-model="personForm.mobile" required aria-label="Mobile" />
            </div>
            <div class="grid gap-1">
              <Label for="person-email">Email</Label>
              <Input id="person-email" v-model="personForm.email" type="email" aria-label="Email" />
            </div>
          </template>
          <div class="grid gap-1">
            <Label for="person-start">Start date</Label>
            <Input id="person-start" v-model="personForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="grid gap-1">
            <Label for="person-reason">Reason</Label>
            <Input id="person-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="savePerson(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !personChecked || blocks.length > 0">Save</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Role</th>
                <th class="px-3 py-2 font-medium">Name</th>
                <th class="px-3 py-2 font-medium">CNIC</th>
                <th class="px-3 py-2 font-medium">Mobile</th>
                <th class="px-3 py-2 font-medium">From</th>
                <th class="px-3 py-2 font-medium">To</th>
                <th class="px-3 py-2 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="officers.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No people yet.</td>
              </tr>
              <tr v-for="row in officers" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.role_label }}</td>
                <td class="px-3 py-2">{{ row.full_name }}</td>
                <td class="px-3 py-2">{{ row.cnic_display || '—' }}</td>
                <td class="px-3 py-2">{{ row.mobile }}</td>
                <td class="px-3 py-2">{{ displayDate(row.start_date) }}</td>
                <td class="px-3 py-2">{{ displayDate(row.end_date) }}</td>
                <td class="px-3 py-2">
                  <Button v-if="canStaff && !row.end_date" type="button" variant="outline" size="sm" @click="startEnd(row)">End</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'staff'" class="grid gap-3">
        <div class="flex flex-wrap items-end gap-2">
          <Button v-if="canStaff" type="button" @click="startPerson('technical_staff')">Add Staff</Button>
          <div class="grid gap-1">
            <Label for="staff-filter">Show</Label>
            <select id="staff-filter" v-model="staffFilter" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Show">
              <option value="current">Current</option>
              <option value="ended">Ended</option>
              <option value="all">All</option>
            </select>
          </div>
        </div>
        <form v-if="panel === 'staff'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="savePerson(false)">
          <h3 class="font-medium">Add technical staff — {{ company.name }}</h3>
          <div class="flex items-end gap-2">
            <div class="grid flex-1 gap-1">
              <Label for="staff-cnic">CNIC</Label>
              <Input id="staff-cnic" v-model="personForm.cnic" required aria-label="CNIC" />
            </div>
            <Button type="button" variant="outline" @click="checkPerson">Check</Button>
          </div>
          <Alert v-for="block in blocks" :key="block.message" variant="destructive">
            <AlertTitle>{{ block.message }}</AlertTitle>
          </Alert>
          <p v-if="personChecked && personFound && blocks.length === 0" class="text-sm">Found: {{ personForm.full_name }}. Details pre-filled.</p>
          <template v-if="personChecked && !personFound">
            <div class="grid gap-1">
              <Label for="staff-name">Name</Label>
              <Input id="staff-name" v-model="personForm.full_name" required aria-label="Name" />
            </div>
            <div class="grid gap-1">
              <Label for="staff-father">Father name</Label>
              <Input id="staff-father" v-model="personForm.father_name" aria-label="Father name" />
            </div>
            <div class="grid gap-1">
              <Label for="staff-mobile">Mobile</Label>
              <Input id="staff-mobile" v-model="personForm.mobile" required aria-label="Mobile" />
            </div>
            <div class="grid gap-1">
              <Label for="staff-email">Email</Label>
              <Input id="staff-email" v-model="personForm.email" type="email" aria-label="Email" />
            </div>
            <div class="grid gap-1">
              <Label for="staff-qualification">Qualification</Label>
              <select id="staff-qualification" v-model="personForm.qualification_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Qualification">
                <option value="">Optional</option>
                <option v-for="item in qualifications" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </div>
            <div class="grid gap-1">
              <Label for="staff-institution">Institution</Label>
              <Input id="staff-institution" v-model="personForm.institution" aria-label="Institution" />
            </div>
            <div class="grid gap-1">
              <Label for="staff-year">Year</Label>
              <Input id="staff-year" v-model="personForm.passing_year" aria-label="Year" />
            </div>
          </template>
          <div class="grid gap-1">
            <Label for="staff-start">Start date</Label>
            <Input id="staff-start" v-model="personForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="grid gap-1">
            <Label for="staff-reason">Reason</Label>
            <Input id="staff-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="savePerson(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !personChecked || blocks.length > 0">Save</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Name</th>
                <th class="px-3 py-2 font-medium">CNIC</th>
                <th class="px-3 py-2 font-medium">Qualification</th>
                <th class="px-3 py-2 font-medium">Mobile</th>
                <th class="px-3 py-2 font-medium">Since</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="staff.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="7">No technical staff.</td>
              </tr>
              <tr v-for="row in staff" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.full_name }}</td>
                <td class="px-3 py-2">{{ row.cnic_display || '—' }}</td>
                <td class="px-3 py-2">{{ row.qualification || '—' }}</td>
                <td class="px-3 py-2">{{ row.mobile }}</td>
                <td class="px-3 py-2">{{ displayDate(row.start_date) }}</td>
                <td class="px-3 py-2">{{ verificationLabel(row.verification_status) }}</td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap gap-2">
                    <Button v-if="canPersons" type="button" variant="outline" size="sm" @click="viewPerson(row)">View Person</Button>
                    <Button v-if="canStaff && !row.end_date" type="button" variant="outline" size="sm" @click="startEnd(row)">End Employment</Button>
                    <Button v-if="canVerify && !row.end_date && row.verification_status === 'pending'" type="button" variant="outline" size="sm" @click="verifyStaff(row)">Verify</Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'premises'" class="grid gap-4">
        <div v-if="canUpdate">
          <Button type="button" @click="startPremise()">Add premise</Button>
        </div>
        <form v-if="panel === 'premise'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="savePremise">
          <div class="grid gap-1">
            <Label for="premise-type">Type</Label>
            <select id="premise-type" v-model="premiseForm.type" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Type">
              <option v-for="[value, label] in premiseTypes" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="premise-address">Address</Label>
            <textarea id="premise-address" v-model="premiseForm.address" rows="2" class="border-input rounded-md border bg-transparent px-3 py-2 text-sm" required aria-label="Address" />
          </div>
          <div class="grid gap-1">
            <Label for="premise-district">District</Label>
            <select id="premise-district" v-model="premiseForm.district_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="District">
              <option value="">Outside Balochistan</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="premise-lat">GPS latitude</Label>
            <Input id="premise-lat" v-model="premiseForm.gps_lat" aria-label="GPS latitude" />
          </div>
          <div class="grid gap-1">
            <Label for="premise-lng">GPS longitude</Label>
            <Input id="premise-lng" v-model="premiseForm.gps_lng" aria-label="GPS longitude" />
          </div>
          <div class="grid gap-1">
            <Label for="premise-phone">Phone</Label>
            <Input id="premise-phone" v-model="premiseForm.phone" aria-label="Phone" />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="premiseForm.is_active" type="checkbox" aria-label="Active">
            Active
          </label>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium">Location / District</th>
                <th class="px-3 py-2 font-medium">GPS</th>
                <th class="px-3 py-2 font-medium">Contact</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="premises.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="4">No premises.</td>
              </tr>
              <tr v-for="row in premises" :key="row.id" class="cursor-pointer border-b last:border-0" @click="canUpdate && startPremise(row)">
                <td class="px-3 py-2">{{ premiseTypes.find(([value]) => value === row.type)?.[1] || row.type }}</td>
                <td class="px-3 py-2">{{ row.address }}<span v-if="row.district_name">, {{ row.district_name }}</span></td>
                <td class="px-3 py-2">{{ row.gps_lat && row.gps_lng ? `${row.gps_lat}, ${row.gps_lng}` : '—' }}</td>
                <td class="px-3 py-2">{{ row.phone || row.contact_name || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-between">
          <h3 class="font-medium">Assets</h3>
          <Button v-if="canUpdate" type="button" variant="outline" @click="startAsset()">Add asset</Button>
        </div>
        <form v-if="panel === 'asset'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="saveAsset">
          <div class="grid gap-1">
            <Label for="asset-type">Type</Label>
            <select id="asset-type" v-model="assetForm.asset_type" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Asset type">
              <option value="movable">Movable</option>
              <option value="immovable">Immovable</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="asset-description">Description</Label>
            <Input id="asset-description" v-model="assetForm.description" required aria-label="Description" />
          </div>
          <div class="grid gap-1">
            <Label for="asset-district">District</Label>
            <select id="asset-district" v-model="assetForm.district_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Asset district">
              <option value="">None</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="asset-value">Estimated value</Label>
            <Input id="asset-value" v-model="assetForm.estimated_value" aria-label="Estimated value" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium">Description</th>
                <th class="px-3 py-2 font-medium">District</th>
                <th class="px-3 py-2 font-medium">Value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="assets.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="4">No assets. Assets are optional.</td>
              </tr>
              <tr v-for="row in assets" :key="row.id" class="cursor-pointer border-b last:border-0" @click="canUpdate && startAsset(row)">
                <td class="px-3 py-2">{{ row.asset_type === 'immovable' ? 'Immovable' : 'Movable' }}</td>
                <td class="px-3 py-2">{{ row.description }}</td>
                <td class="px-3 py-2">{{ row.district_name || '—' }}</td>
                <td class="px-3 py-2">{{ row.estimated_value || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'products'" class="grid gap-3">
        <div v-if="canProducts">
          <Button type="button" @click="startProduct()">Add product</Button>
        </div>
        <form v-if="panel === 'product'" class="grid max-w-xl gap-3 rounded-xl border p-4" @submit.prevent="saveProduct">
          <div class="grid gap-1">
            <Label for="brand-name">Brand</Label>
            <Input id="brand-name" v-model="productForm.brand_name" required aria-label="Brand" />
          </div>
          <div class="grid gap-1">
            <Label for="generic-product">Generic product</Label>
            <select id="generic-product" v-model="productForm.product_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" required aria-label="Generic product">
              <option value="">Choose</option>
              <option v-for="item in productChoices" :key="item.id" :value="item.id">{{ item.label }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="dpp-no">DPP registration no</Label>
            <Input id="dpp-no" v-model="productForm.dpp_registration_no" aria-label="DPP registration no" />
          </div>
          <div class="grid gap-1">
            <Label for="product-source">Source</Label>
            <select id="product-source" v-model="productForm.source" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Source">
              <option value="own_import">Own import</option>
              <option value="purchase_agreement">Purchase agreement</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="productForm.sample_provided" type="checkbox" aria-label="Sample provided">
            Sample provided
          </label>
          <div v-if="productForm.id" class="grid gap-1">
            <Label for="product-delete-reason">Remove reason</Label>
            <Input id="product-delete-reason" v-model="deleteReason" aria-label="Remove reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
            <Button v-if="productForm.id" type="button" variant="outline" :disabled="saving" @click="removeProduct">Remove</Button>
          </div>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-card">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Brand</th>
                <th class="px-3 py-2 font-medium">Generic / Conc. / Form.</th>
                <th class="px-3 py-2 font-medium">DPP Reg</th>
                <th class="px-3 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="products.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="4">No products.</td>
              </tr>
              <tr v-for="row in products" :key="row.id" class="cursor-pointer border-b last:border-0" @click="canProducts && startProduct(row)">
                <td class="px-3 py-2">{{ row.brand_name }}</td>
                <td class="px-3 py-2">{{ [row.generic_name, row.concentration, row.formulation].filter(Boolean).join(' ') || '—' }}</td>
                <td class="px-3 py-2">{{ row.dpp_registration_no || '—' }}</td>
                <td class="px-3 py-2">{{ row.status }}</td>
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
            <input id="document-file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="text-sm" aria-label="File" @change="documentForm.file = $event.target.files?.[0] || null">
            <p class="text-xs text-muted-foreground">PDF, JPG, or PNG. The size limit is the Maximum upload size setting.</p>
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
            <Label for="document-issue">Issue date</Label>
            <Input id="document-issue" v-model="documentForm.issue_date" type="date" aria-label="Issue date" />
          </div>
          <div class="grid gap-1">
            <Label for="document-expiry">Expiry date</Label>
            <Input id="document-expiry" v-model="documentForm.expiry_date" type="date" aria-label="Expiry date" />
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
                    <Button v-if="canUpload" type="button" variant="outline" @click="startDocument('edit', row)">Edit</Button>
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
            <thead class="border-b text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Version</th>
                <th class="px-3 py-2 font-medium">Title</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 font-medium">Current</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in historyRows" :key="row.id" class="border-b last:border-0">
                <td class="px-3 py-2">{{ row.version_no }}</td>
                <td class="px-3 py-2">{{ row.title }}</td>
                <td class="px-3 py-2">{{ verificationLabel(row.verification_status) }}</td>
                <td class="px-3 py-2">{{ row.current ? 'Yes' : 'No' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-else-if="tab === 'licenses'" class="grid gap-3">
        <div v-if="canIssue" class="flex justify-end">
          <Button type="button" variant="outline" @click="router.push({ name: 'previous-license', query: { type: 'company', id: route.params.id } })">Record previous license</Button>
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
          <Label for="suspend-reason">Reason</Label>
          <Input id="suspend-reason" v-model="suspendForm.reason" required minlength="3" aria-label="Reason" />
        </div>
        <div class="grid gap-1">
          <Label for="suspend-order">Order number</Label>
          <Input id="suspend-order" v-model="suspendForm.order_no" required aria-label="Order number" />
        </div>
        <div class="grid gap-1">
          <Label for="suspend-date">Effective date</Label>
          <Input id="suspend-date" v-model="suspendForm.effective_date" type="date" required aria-label="Effective date" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="showSuspend = false">Cancel</Button>
        </div>
      </form>
    </template>
  </div>
</template>
