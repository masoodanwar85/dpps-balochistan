<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, ensureCsrf, firstError, upload } from '@/lib/api'
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
const canViewDealers = computed(() => auth.can('dealers.view'))

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
const csrRndFiles = ref([])
const csrRndKinds = ref([])
const csrRndForm = ref(null)
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
const showCsrRnd = computed(() => Boolean(company.value?.csr || company.value?.rnd))
const tabs = computed(() => {
  const items = [
    ['overview', 'Overview'],
    ['people', 'People'],
    ['staff', 'Tech Staff'],
    ['premises', 'Premises'],
    ['products', 'Products'],
    ['licenses', 'Licenses'],
    ['applications', 'Applications'],
    ['documents', 'Documents'],
  ]

  if (showCsrRnd.value) {
    items.push(['csr-rnd', 'CSR/R&D'])
  }

  items.push(['portal', 'Portal Users'], ['activity', 'Activity'])

  return items
})
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

async function loadCsrRnd() {
  const { response, payload } = await api(`/api/v1/companies/${companyId.value}/csr-rnd`)

  if (response.ok) {
    csrRndFiles.value = payload.data || []
    csrRndKinds.value = payload.meta?.kinds || []
  }
}

function startCsrRnd() {
  panel.value = 'csr-rnd'
  formError.value = ''
  csrRndForm.value = {
    kind: csrRndKinds.value[0]?.value || '',
    title: '',
    file: null,
  }
}

async function saveCsrRnd() {
  formError.value = ''
  saving.value = true
  const body = new FormData()
  body.append('kind', csrRndForm.value.kind)
  body.append('title', csrRndForm.value.title)
  body.append('file', csrRndForm.value.file)
  const { response, payload } = await upload(`/api/v1/companies/${companyId.value}/csr-rnd`, body)
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not upload this file.'

    return
  }

  panel.value = ''
  notice.value = 'CSR/R&D file uploaded.'
  await loadCsrRnd()
}

async function viewCsrRnd(row) {
  const { response, payload } = await api(`/api/v1/csr-rnd-files/${row.id}/download-url`)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The file could not be opened.'

    return
  }

  window.open(payload.data.url, '_blank', 'noopener')
}

function selectTab(key) {
  tab.value = key
  panel.value = ''

  if (key === 'licenses') {
    loadLicenses()
  }

  if (key === 'csr-rnd') {
    loadCsrRnd()
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
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="company">
      <PageSection accent="emerald" eyebrow="Company profile" :title="`${company.name} (${company.company_code})`">
        <template #actions>
          <Button v-if="canUpdate" type="button" variant="outline" size="sm" @click="router.push({ name: 'companies', query: { edit: company.id } })">Edit</Button>
          <Button v-if="canApply" type="button" variant="outline" size="sm" @click="router.push({ name: 'applications', query: { new: 'company', id: company.id } })">New Application</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>New Application</Button>
          <Button v-if="canSuspendLicense" type="button" variant="outline" size="sm" @click="startSuspend">Suspend</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>Suspend</Button>
          <Button v-if="profile.license?.id" type="button" variant="outline" size="sm" @click="openCertificate">Certificate</Button>
          <Button v-else type="button" variant="outline" size="sm" disabled>Certificate</Button>
          <Button type="button" variant="outline" size="sm" @click="tab = 'activity'">Activity</Button>
          <Button type="button" variant="outline" size="sm" @click="router.push({ name: 'companies' })">Back</Button>
        </template>
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
          <StatusBadge :value="company.status" :label="statusLabel(company.status)" />
          <template v-if="profile.license">
            <span>License {{ profile.license.license_no }}</span>
            <span>{{ displayDate(profile.license.valid_from) }} → {{ displayDate(profile.license.valid_to) }}</span>
            <span v-if="profile.license.days_remaining !== null">({{ profile.license.days_remaining }} d)</span>
          </template>
          <StatusBadge v-if="profile.license?.documents_status === 'incomplete'" value="incomplete" label="Docs incomplete" />
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button
            v-for="[key, label] in tabs"
            :key="key"
            type="button"
            class="rounded-md px-3 py-1.5 text-sm ring-1 transition"
            :class="tab === key ? 'bg-emerald-50 font-medium text-emerald-900 ring-emerald-200' : 'bg-background text-muted-foreground ring-border hover:bg-muted'"
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
      <PageSection v-if="panel === 'end'" accent="rose" eyebrow="Staff" :title="`End employment — ${endForm.name}`">
        <form class="grid max-w-xl gap-3" @submit.prevent="confirmEnd">
          <div class="dpps-field">
            <Label for="end-date">End date</Label>
            <Input id="end-date" v-model="endForm.end_date" type="date" required aria-label="End date" />
          </div>
          <div class="dpps-field">
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
      </PageSection>

      <template v-if="tab === 'overview'">
        <div class="grid gap-6 lg:grid-cols-2">
          <PageSection accent="emerald" eyebrow="Profile" title="Company profile">
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                <p class="text-xs font-medium text-emerald-800">Legal type</p>
                <p class="mt-1 font-semibold text-emerald-950">{{ legalTypes[company.legal_type] || company.legal_type }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium text-slate-600">NTN</p>
                <p class="mt-1 font-semibold text-slate-950">{{ company.ntn || '—' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium text-slate-600">Incorporation no</p>
                <p class="mt-1 font-semibold text-slate-950">{{ company.incorporation_no || '—' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium text-slate-600">Incorporation date</p>
                <p class="mt-1 font-semibold text-slate-950">{{ company.incorporation_date ? displayDate(company.incorporation_date) : '—' }}</p>
              </div>
              <div class="rounded-lg border px-3 py-3" :class="company.pcpa_member ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'">
                <p class="text-xs font-medium" :class="company.pcpa_member ? 'text-emerald-800' : 'text-slate-600'">PCPA</p>
                <p class="mt-1 font-semibold" :class="company.pcpa_member ? 'text-emerald-950' : 'text-slate-950'">{{ company.pcpa_member ? 'Yes' : 'No' }}</p>
              </div>
              <div class="rounded-lg border px-3 py-3" :class="company.croplife_member ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-slate-50'">
                <p class="text-xs font-medium" :class="company.croplife_member ? 'text-sky-800' : 'text-slate-600'">CropLife</p>
                <p class="mt-1 font-semibold" :class="company.croplife_member ? 'text-sky-950' : 'text-slate-950'">{{ company.croplife_member ? 'Yes' : 'No' }}</p>
              </div>
            </div>
          </PageSection>

          <PageSection accent="sky" eyebrow="Contact" title="Head office">
            <div class="grid gap-3">
              <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3 sm:col-span-2">
                <p class="text-xs font-medium text-sky-800">Address</p>
                <p class="mt-1 font-semibold text-sky-950">
                  {{ company.head_office_address }}, {{ company.city }}<span v-if="company.province_name">, {{ company.province_name }}</span>
                </p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">Landline</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ company.landline || '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">Mobile</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ company.mobile || '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">Email</p>
                  <p class="mt-1 font-semibold text-slate-950 break-all">{{ company.email || '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                  <p class="text-xs font-medium text-slate-600">Contact person</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ profile.contacts.map((row) => row.name).join(', ') || '—' }}</p>
                </div>
              </div>
            </div>
          </PageSection>
        </div>

        <PageSection accent="sky" eyebrow="Dealers" title="Linked dealers">
          <div class="dpps-table-wrap rounded-none border-0">
            <table class="dpps-table">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Shop</th>
                  <th>District</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(profile.dealers || []).length">
                  <td class="text-muted-foreground" colspan="4">No dealers linked.</td>
                </tr>
                <tr
                  v-for="row in profile.dealers || []"
                  :key="row.id"
                  :class="canViewDealers ? 'dpps-row-link' : ''"
                  @click="canViewDealers && router.push(`/dealers/${row.id}`)"
                >
                  <td class="font-medium">{{ row.dealer_code }}</td>
                  <td>{{ row.shop_name }}</td>
                  <td>{{ row.district_name || '—' }}</td>
                  <td><StatusBadge :value="row.status" :label="row.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </PageSection>

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
              <p class="text-xs font-medium text-slate-600">Days remaining</p>
              <p class="mt-1 font-semibold text-slate-950 tabular-nums">{{ profile.license.days_remaining ?? '—' }}</p>
            </div>
          </div>
        </PageSection>

        <PageSection v-if="profile.alerts?.length" accent="amber" eyebrow="Alerts" title="Attention needed">
          <div class="grid gap-2">
            <Alert v-for="alert in profile.alerts" :key="alert.kind">
              <AlertTitle>{{ alert.message }}</AlertTitle>
            </Alert>
          </div>
        </PageSection>
      </template>

      <template v-else-if="tab === 'people'">
      <PageSection accent="sky" eyebrow="People" title="CEO and directors">
        <template #actions>
          <Button v-if="canStaff" type="button" size="sm" @click="startPerson('ceo')">Add person</Button>
        </template>
      </PageSection>
      <PageSection v-if="panel === 'person'" accent="violet" eyebrow="People" title="Add person">
        <form class="grid max-w-xl gap-3" @submit.prevent="savePerson(false)">
          <div class="dpps-field">
            <Label for="person-role">Role</Label>
            <select id="person-role" v-model="personForm.role" class="dpps-select" aria-label="Role">
              <option v-for="[value, label] in officerRoles" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <div class="grid flex-1 gap-1.5">
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
            <div class="dpps-field">
              <Label for="person-name">Name</Label>
              <Input id="person-name" v-model="personForm.full_name" required aria-label="Name" />
            </div>
            <div class="dpps-field">
              <Label for="person-father">Father name</Label>
              <Input id="person-father" v-model="personForm.father_name" aria-label="Father name" />
            </div>
            <div class="dpps-field">
              <Label for="person-mobile">Mobile</Label>
              <Input id="person-mobile" v-model="personForm.mobile" required aria-label="Mobile" />
            </div>
            <div class="dpps-field">
              <Label for="person-email">Email</Label>
              <Input id="person-email" v-model="personForm.email" type="email" aria-label="Email" />
            </div>
          </template>
          <div class="dpps-field">
            <Label for="person-start">Start date</Label>
            <Input id="person-start" v-model="personForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="dpps-field">
            <Label for="person-reason">Reason</Label>
            <Input id="person-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="savePerson(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !personChecked || blocks.length > 0">Save</Button>
          </div>
        </form>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Role</th>
                <th>Name</th>
                <th>CNIC</th>
                <th>Mobile</th>
                <th>From</th>
                <th>To</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="officers.length === 0">
                <td class="text-muted-foreground" colspan="7">No people yet.</td>
              </tr>
              <tr v-for="row in officers" :key="row.id">
                <td>{{ row.role_label }}</td>
                <td class="font-medium">{{ row.full_name }}</td>
                <td>{{ row.cnic_display || '—' }}</td>
                <td>{{ row.mobile }}</td>
                <td class="tabular-nums">{{ displayDate(row.start_date) }}</td>
                <td class="tabular-nums">{{ displayDate(row.end_date) }}</td>
                <td>
                  <Button v-if="canStaff && !row.end_date" type="button" variant="outline" size="sm" @click="startEnd(row)">End</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'staff'">
      <PageSection accent="orange" eyebrow="Staff" title="Technical staff">
        <template #actions>
          <Button v-if="canStaff" type="button" size="sm" @click="startPerson('technical_staff')">Add Staff</Button>
        </template>
        <div class="dpps-field max-w-xs">
          <Label for="staff-filter">Show</Label>
          <select id="staff-filter" v-model="staffFilter" class="dpps-select" aria-label="Show">
            <option value="current">Current</option>
            <option value="ended">Ended</option>
            <option value="all">All</option>
          </select>
        </div>
      </PageSection>
      <PageSection v-if="panel === 'staff'" accent="violet" eyebrow="Staff" :title="`Add technical staff — ${company.name}`">
        <form class="grid max-w-xl gap-3" @submit.prevent="savePerson(false)">
          <div class="flex items-end gap-2">
            <div class="grid flex-1 gap-1.5">
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
            <div class="dpps-field">
              <Label for="staff-name">Name</Label>
              <Input id="staff-name" v-model="personForm.full_name" required aria-label="Name" />
            </div>
            <div class="dpps-field">
              <Label for="staff-father">Father name</Label>
              <Input id="staff-father" v-model="personForm.father_name" aria-label="Father name" />
            </div>
            <div class="dpps-field">
              <Label for="staff-mobile">Mobile</Label>
              <Input id="staff-mobile" v-model="personForm.mobile" required aria-label="Mobile" />
            </div>
            <div class="dpps-field">
              <Label for="staff-email">Email</Label>
              <Input id="staff-email" v-model="personForm.email" type="email" aria-label="Email" />
            </div>
            <div class="dpps-field">
              <Label for="staff-qualification">Qualification</Label>
              <select id="staff-qualification" v-model="personForm.qualification_id" class="dpps-select" aria-label="Qualification">
                <option value="">Optional</option>
                <option v-for="item in qualifications" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
            </div>
            <div class="dpps-field">
              <Label for="staff-institution">Institution</Label>
              <Input id="staff-institution" v-model="personForm.institution" aria-label="Institution" />
            </div>
            <div class="dpps-field">
              <Label for="staff-year">Year</Label>
              <Input id="staff-year" v-model="personForm.passing_year" aria-label="Year" />
            </div>
          </template>
          <div class="dpps-field">
            <Label for="staff-start">Start date</Label>
            <Input id="staff-start" v-model="personForm.start_date" type="date" required aria-label="Start date" />
          </div>
          <Alert v-for="warning in warnings" :key="warning">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div v-if="warnings.length" class="dpps-field">
            <Label for="staff-reason">Reason</Label>
            <Input id="staff-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving || blocks.length > 0" @click="savePerson(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving || !personChecked || blocks.length > 0">Save</Button>
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
                <th>Qualification</th>
                <th>Mobile</th>
                <th>Since</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="staff.length === 0">
                <td class="text-muted-foreground" colspan="7">No technical staff.</td>
              </tr>
              <tr v-for="row in staff" :key="row.id">
                <td class="font-medium">{{ row.full_name }}</td>
                <td>{{ row.cnic_display || '—' }}</td>
                <td>{{ row.qualification || '—' }}</td>
                <td>{{ row.mobile }}</td>
                <td class="tabular-nums">{{ displayDate(row.start_date) }}</td>
                <td><StatusBadge :value="row.verification_status" :label="verificationLabel(row.verification_status)" /></td>
                <td>
                  <div class="flex flex-wrap gap-1">
                    <Button v-if="canPersons" type="button" variant="outline" size="sm" @click="viewPerson(row)">View Person</Button>
                    <Button v-if="canStaff && !row.end_date" type="button" variant="outline" size="sm" @click="startEnd(row)">End Employment</Button>
                    <Button v-if="canVerify && !row.end_date && row.verification_status === 'pending'" type="button" variant="outline" size="sm" @click="verifyStaff(row)">Verify</Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'premises'">
      <PageSection accent="violet" eyebrow="Sites" title="Premises">
        <template #actions>
          <Button v-if="canUpdate" type="button" size="sm" @click="startPremise()">Add premise</Button>
        </template>
      </PageSection>
      <PageSection v-if="panel === 'premise'" accent="violet" eyebrow="Sites" title="Premise">
        <form class="grid max-w-xl gap-3" @submit.prevent="savePremise">
          <div class="dpps-field">
            <Label for="premise-type">Type</Label>
            <select id="premise-type" v-model="premiseForm.type" class="dpps-select" aria-label="Type">
              <option v-for="[value, label] in premiseTypes" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="premise-address">Address</Label>
            <textarea id="premise-address" v-model="premiseForm.address" rows="2" class="dpps-textarea" required aria-label="Address" />
          </div>
          <div class="dpps-field">
            <Label for="premise-district">District</Label>
            <select id="premise-district" v-model="premiseForm.district_id" class="dpps-select" aria-label="District">
              <option value="">Outside Balochistan</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="premise-lat">GPS latitude</Label>
            <Input id="premise-lat" v-model="premiseForm.gps_lat" aria-label="GPS latitude" />
          </div>
          <div class="dpps-field">
            <Label for="premise-lng">GPS longitude</Label>
            <Input id="premise-lng" v-model="premiseForm.gps_lng" aria-label="GPS longitude" />
          </div>
          <div class="dpps-field">
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
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Location / District</th>
                <th>GPS</th>
                <th>Contact</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="premises.length === 0">
                <td class="text-muted-foreground" colspan="4">No premises.</td>
              </tr>
              <tr v-for="row in premises" :key="row.id" class="dpps-row-link" @click="canUpdate && startPremise(row)">
                <td>{{ premiseTypes.find(([value]) => value === row.type)?.[1] || row.type }}</td>
                <td>{{ row.address }}<span v-if="row.district_name">, {{ row.district_name }}</span></td>
                <td>{{ row.gps_lat && row.gps_lng ? `${row.gps_lat}, ${row.gps_lng}` : '—' }}</td>
                <td>{{ row.phone || row.contact_name || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      <PageSection accent="amber" eyebrow="Assets" title="Assets">
        <template #actions>
          <Button v-if="canUpdate" type="button" variant="outline" size="sm" @click="startAsset()">Add asset</Button>
        </template>
      </PageSection>
      <PageSection v-if="panel === 'asset'" accent="amber" eyebrow="Assets" title="Asset">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveAsset">
          <div class="dpps-field">
            <Label for="asset-type">Type</Label>
            <select id="asset-type" v-model="assetForm.asset_type" class="dpps-select" aria-label="Asset type">
              <option value="movable">Movable</option>
              <option value="immovable">Immovable</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="asset-description">Description</Label>
            <Input id="asset-description" v-model="assetForm.description" required aria-label="Description" />
          </div>
          <div class="dpps-field">
            <Label for="asset-district">District</Label>
            <select id="asset-district" v-model="assetForm.district_id" class="dpps-select" aria-label="Asset district">
              <option value="">None</option>
              <option v-for="district in districts" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="asset-value">Estimated value</Label>
            <Input id="asset-value" v-model="assetForm.estimated_value" aria-label="Estimated value" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Description</th>
                <th>District</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="assets.length === 0">
                <td class="text-muted-foreground" colspan="4">No assets. Assets are optional.</td>
              </tr>
              <tr v-for="row in assets" :key="row.id" class="dpps-row-link" @click="canUpdate && startAsset(row)">
                <td>{{ row.asset_type === 'immovable' ? 'Immovable' : 'Movable' }}</td>
                <td class="font-medium">{{ row.description }}</td>
                <td>{{ row.district_name || '—' }}</td>
                <td>{{ row.estimated_value || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'products'">
      <PageSection accent="emerald" eyebrow="Catalog" title="Products">
        <template #actions>
          <Button v-if="canProducts" type="button" size="sm" @click="startProduct()">Add product</Button>
        </template>
      </PageSection>
      <PageSection v-if="panel === 'product'" accent="emerald" eyebrow="Catalog" title="Product">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveProduct">
          <div class="dpps-field">
            <Label for="brand-name">Brand</Label>
            <Input id="brand-name" v-model="productForm.brand_name" required aria-label="Brand" />
          </div>
          <div class="dpps-field">
            <Label for="generic-product">Product</Label>
            <select id="generic-product" v-model="productForm.product_id" class="dpps-select" required aria-label="Product">
              <option value="">Choose</option>
              <option v-for="item in productChoices" :key="item.id" :value="item.id">{{ item.label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="dpp-no">DPP registration no</Label>
            <Input id="dpp-no" v-model="productForm.dpp_registration_no" aria-label="DPP registration no" />
          </div>
          <div class="dpps-field">
            <Label for="product-source">Source</Label>
            <select id="product-source" v-model="productForm.source" class="dpps-select" aria-label="Source">
              <option value="own_import">Own import</option>
              <option value="purchase_agreement">Purchase agreement</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="productForm.sample_provided" type="checkbox" aria-label="Sample provided">
            Sample provided
          </label>
          <div v-if="productForm.id" class="dpps-field">
            <Label for="product-delete-reason">Remove reason</Label>
            <Input id="product-delete-reason" v-model="deleteReason" aria-label="Remove reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
            <Button v-if="productForm.id" type="button" variant="outline" :disabled="saving" @click="removeProduct">Remove</Button>
          </div>
        </form>
      </PageSection>
      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Brand</th>
                <th>Market name / Conc. / Form.</th>
                <th>DPP Reg</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="products.length === 0">
                <td class="text-muted-foreground" colspan="4">No products.</td>
              </tr>
              <tr v-for="row in products" :key="row.id" class="dpps-row-link" @click="canProducts && startProduct(row)">
                <td class="font-medium">{{ row.brand_name }}</td>
                <td>{{ row.product_label || '—' }}</td>
                <td>{{ row.dpp_registration_no || '—' }}</td>
                <td><StatusBadge :value="row.status" :label="row.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'csr-rnd'">
      <PageSection accent="amber" eyebrow="CSR / R&D" title="CSR/R&D">
        <template #actions>
          <Button v-if="canUpload" type="button" size="sm" @click="startCsrRnd">Upload</Button>
        </template>
        <p class="text-sm text-muted-foreground">Photos or documents for CSR and R&amp;D. Files stay on record if both flags are later turned off.</p>
      </PageSection>
      <PageSection v-if="panel === 'csr-rnd'" accent="amber" eyebrow="CSR / R&D" title="Upload file">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveCsrRnd">
          <div class="dpps-field">
            <Label for="csr-rnd-kind">Type</Label>
            <select id="csr-rnd-kind" v-model="csrRndForm.kind" class="dpps-select" required aria-label="Type">
              <option value="">Choose</option>
              <option v-for="item in csrRndKinds" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="csr-rnd-title">Title</Label>
            <Input id="csr-rnd-title" v-model="csrRndForm.title" required aria-label="Title" />
          </div>
          <div class="dpps-field">
            <Label for="csr-rnd-file">File</Label>
            <input id="csr-rnd-file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="text-sm" required aria-label="File" @change="csrRndForm.file = $event.target.files?.[0] || null">
            <p class="text-xs text-muted-foreground">PDF, JPG, or PNG.</p>
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="panel = ''">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
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
                <th>File</th>
                <th>Via</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="csrRndFiles.length === 0">
                <td class="text-muted-foreground" colspan="6">No CSR/R&amp;D files.</td>
              </tr>
              <tr v-for="row in csrRndFiles" :key="row.id">
                <td class="font-medium">{{ row.title }}</td>
                <td>{{ row.kind_label }}</td>
                <td>{{ row.original_name }}</td>
                <td>{{ viaLabel(row.uploaded_via) }}</td>
                <td class="tabular-nums">{{ displayDate(row.created_at) }}</td>
                <td>
                  <Button v-if="canDownload" type="button" variant="outline" size="sm" @click="viewCsrRnd(row)">View</Button>
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
            <input id="document-file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="text-sm" aria-label="File" @change="documentForm.file = $event.target.files?.[0] || null">
            <p class="text-xs text-muted-foreground">PDF, JPG, or PNG. The size limit is the Maximum upload size setting.</p>
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
            <Label for="document-issue">Issue date</Label>
            <Input id="document-issue" v-model="documentForm.issue_date" type="date" aria-label="Issue date" />
          </div>
          <div class="dpps-field">
            <Label for="document-expiry">Expiry date</Label>
            <Input id="document-expiry" v-model="documentForm.expiry_date" type="date" aria-label="Expiry date" />
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
                <td class="text-muted-foreground" colspan="7">No documents.</td>
              </tr>
              <tr v-for="row in documents" :key="row.id">
                <td class="font-medium">{{ row.title }}</td>
                <td>{{ row.type_name }}</td>
                <td class="tabular-nums">{{ displayDate(row.expiry_date) }}</td>
                <td>{{ row.version_no }}</td>
                <td>{{ viaLabel(row.uploaded_via) }}</td>
                <td><StatusBadge :value="row.verification_status" :label="verificationLabel(row.verification_status)" /></td>
                <td>
                  <div class="flex flex-wrap gap-1">
                    <Button v-if="canDownload" type="button" variant="outline" size="sm" @click="viewDocument(row)">View</Button>
                    <Button type="button" variant="outline" size="sm" @click="showHistory(row)">History</Button>
                    <Button v-if="canUpload" type="button" variant="outline" size="sm" @click="startDocument('replace', row)">Replace</Button>
                    <Button v-if="canUpload" type="button" variant="outline" size="sm" @click="startDocument('edit', row)">Edit</Button>
                    <Button v-if="canVerifyDocument && row.verification_status === 'pending'" type="button" variant="outline" size="sm" :disabled="saving" @click="verifyDocument(row)">Verify</Button>
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
            <thead>
              <tr>
                <th>Version</th>
                <th>Title</th>
                <th>Status</th>
                <th>Current</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in historyRows" :key="row.id">
                <td>{{ row.version_no }}</td>
                <td>{{ row.title }}</td>
                <td><StatusBadge :value="row.verification_status" :label="verificationLabel(row.verification_status)" /></td>
                <td><StatusBadge :value="row.current" :label="row.current ? 'Yes' : 'No'" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <template v-else-if="tab === 'licenses'">
      <PageSection accent="violet" eyebrow="Issuance" title="Licenses">
        <template #actions>
          <Button v-if="canIssue" type="button" variant="outline" size="sm" @click="router.push({ name: 'previous-license', query: { type: 'company', id: route.params.id } })">Record previous license</Button>
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
                <td class="text-muted-foreground" colspan="6">No licenses.</td>
              </tr>
              <tr v-for="row in licenseRows" :key="row.id">
                <td class="font-medium">{{ row.license_no }}</td>
                <td><StatusBadge :value="row.license_kind" :label="row.license_kind" /></td>
                <td class="tabular-nums">{{ displayDate(row.valid_from) }}</td>
                <td class="tabular-nums">{{ displayDate(row.valid_to) }}</td>
                <td><StatusBadge :value="row.status" :label="row.status" /></td>
                <td><StatusBadge :value="row.documents_status" :label="row.documents_status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <PageSection v-else-if="laterTabs.includes(tab)" accent="slate" eyebrow="Coming later" title="Not available yet">
        <p class="text-sm text-muted-foreground">This tab is not available yet.</p>
      </PageSection>

      <template v-else-if="tab === 'activity'">
      <PageSection accent="slate" eyebrow="Audit" title="Activity" content-class="px-0 pt-0 pb-0">
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
                <td class="text-muted-foreground" colspan="4">No activity yet.</td>
              </tr>
              <tr v-for="row in activity" :key="row.id">
                <td class="tabular-nums">{{ row.created_at ? displayDate(row.created_at.slice(0, 10)) : '—' }}</td>
                <td>{{ row.action }}</td>
                <td>{{ row.description }}</td>
                <td>{{ row.user_name || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      </template>

      <PageSection v-if="showSuspend && profile.license" accent="orange" eyebrow="License" :title="`Suspend ${profile.license.license_no}`">
        <form class="grid max-w-xl gap-3" @submit.prevent="saveSuspend">
          <Alert v-if="licenseError" variant="destructive">
            <AlertTitle>{{ licenseError }}</AlertTitle>
          </Alert>
          <div class="dpps-field">
            <Label for="suspend-reason">Reason</Label>
            <Input id="suspend-reason" v-model="suspendForm.reason" required minlength="3" aria-label="Reason" />
          </div>
          <div class="dpps-field">
            <Label for="suspend-order">Order number</Label>
            <Input id="suspend-order" v-model="suspendForm.order_no" required aria-label="Order number" />
          </div>
          <div class="dpps-field">
            <Label for="suspend-date">Effective date</Label>
            <Input id="suspend-date" v-model="suspendForm.effective_date" type="date" required aria-label="Effective date" />
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
