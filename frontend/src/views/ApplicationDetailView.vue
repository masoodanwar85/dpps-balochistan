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
const canProcess = computed(() => auth.can('applications.process'))
const canCreate = computed(() => auth.can('applications.create'))
const canReject = computed(() => auth.can('applications.reject'))
const canUpload = computed(() => auth.can('documents.upload'))
const canEnterPenalty = computed(() => auth.can('penalties.enter'))
const canWaive = computed(() => auth.can('penalties.waive'))
const canVerifyChallan = computed(() => auth.can('challans.verify'))
const canAddChallan = computed(() => canProcess.value || canVerifyChallan.value)
const canHeader = computed(() => canProcess.value || canCreate.value)
const canIssue = computed(() => auth.can('licenses.issue') && !closed.value && currentStage.value?.code === 'issuance')

const detail = ref(null)
const tab = ref('checklist')
const loadError = ref('')
const notice = ref('')
const header = ref({ diary_no: '', received_at: '', total_pages: '' })
const skipRemarks = ref('')
const showSkip = ref(false)
const closeReason = ref('')
const closeMode = ref('')
const deficientItem = ref(null)
const deficientRemarks = ref('')
const uploadItem = ref(null)
const uploadForm = ref(null)
const warningReason = ref('')
const warnings = ref([])
const activity = ref([])
const saving = ref(false)
const showPenalty = ref(false)
const penaltyForm = ref({ penalty_type: 'other', basis: '', standard_amount: '', final_amount: '', waiver_reason: '', waiver_order_no: '' })
const showChallan = ref(false)
const challanForm = ref({ challan_no: '', bank_name: '', branch: '', payment_date: '', amount: '', document_type_id: '', title: 'Treasury challan', file: null })
const rejectChallanId = ref(null)
const challanRemarks = ref('')
const showIssue = ref(false)
const issueReason = ref('')
const issueWarnings = ref([])

const penaltyReduced = computed(() => {
  const standard = penaltyForm.value.standard_amount
  const finalAmount = penaltyForm.value.final_amount

  return standard !== '' && finalAmount !== '' && Number(finalAmount) < Number(standard)
})
const selectedHelper = computed(() => detail.value?.fees?.helpers?.find((row) => row.penalty_type === penaltyForm.value.penalty_type))
const selectedRate = computed(() => detail.value?.fees?.rates?.[penaltyForm.value.penalty_type] || null)

const application = computed(() => detail.value?.application || null)
const currentStage = computed(() => application.value?.current_stage || null)
const canActOnStage = computed(() => currentStage.value && auth.can(currentStage.value.required_permission))
const closed = computed(() => ['issued', 'rejected', 'withdrawn'].includes(application.value?.status))
const checklistOpen = computed(() => !['rejected', 'withdrawn'].includes(application.value?.status))
const issuance = computed(() => detail.value?.issuance || null)
const issueReady = computed(() => canIssue.value && (issuance.value?.blockers || []).length === 0)

function rupees(value) {
  const number = Number(value || 0)

  return `Rs ${number.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function penaltyLabel(value) {
  return ({
    late_renewal: 'Late renewal',
    no_technical_staff: 'No technical staff',
    restoration: 'Restoration',
    other: 'Other',
  })[value] || value
}

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')
  return day && month && year ? `${day}-${month}-${year}` : value
}

function statusLabel(value) {
  return ({
    pending: 'Pending',
    submitted: 'Submitted',
    verified: 'Verified',
    deficient: 'Deficient',
    not_applicable: 'N/A',
    open: 'Open',
    resolved: 'Resolved',
    lapsed: 'Lapsed',
    under_review: 'Under review',
    deficiency_issued: 'Deficiency issued',
    fee_pending: 'Fee pending',
    ready_to_issue: 'Ready to issue',
    issued: 'Issued',
    rejected: 'Rejected',
    withdrawn: 'Withdrawn',
  })[value] || value
}

async function load() {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The application could not be loaded.'
    return
  }

  detail.value = payload.data
  header.value = {
    diary_no: payload.data.application.diary_no || '',
    received_at: payload.data.application.received_at || '',
    total_pages: payload.data.application.total_pages ?? '',
  }
}

async function saveHeader() {
  saving.value = true
  notice.value = ''
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}`, {
    method: 'PUT',
    body: {
      diary_no: header.value.diary_no || null,
      received_at: header.value.received_at || null,
      total_pages: header.value.total_pages === '' ? null : Number(header.value.total_pages),
    },
  })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The application could not be saved.'
    return
  }

  detail.value = payload.data
  notice.value = 'Application updated.'
}

async function completeStage() {
  saving.value = true
  notice.value = ''
  const stageId = currentStage.value.id
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/stages/${stageId}/complete`, {
    method: 'POST',
    body: {},
  })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The stage could not be completed.'
    return
  }

  detail.value = payload.data
  notice.value = 'Stage completed.'
}

async function skipStage() {
  saving.value = true
  notice.value = ''
  const stageId = currentStage.value.id
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/stages/${stageId}/skip`, {
    method: 'POST',
    body: { remarks: skipRemarks.value },
  })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The stage could not be skipped.'
    return
  }

  detail.value = payload.data
  showSkip.value = false
  skipRemarks.value = ''
  notice.value = 'Stage skipped.'
}

async function setItem(item, status, remarks = null) {
  notice.value = ''
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/checklist-items/${item.id}`, {
    method: 'PUT',
    body: {
      status,
      officer_remarks: remarks,
      page_count: item.page_count,
    },
  })

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The checklist item could not be updated.'
    return
  }

  detail.value = payload.data
}

async function savePages(item, event) {
  const value = event.target.value
  item.page_count = value === '' ? null : Number(value)

  if (!canProcess.value || !['verified', 'deficient', 'not_applicable'].includes(item.status)) {
    return
  }

  await setItem(item, item.status, item.officer_remarks)
}

function startDeficient(item) {
  deficientItem.value = item
  deficientRemarks.value = ''
  notice.value = ''
}

async function saveDeficient() {
  await setItem(deficientItem.value, 'deficient', deficientRemarks.value)

  if (!notice.value) {
    deficientItem.value = null
  }
}

function startUpload(item) {
  warnings.value = []
  warningReason.value = ''
  uploadItem.value = item
  uploadForm.value = {
    document_type_id: '',
    title: item.title,
    issue_date: '',
    expiry_date: '',
    file: null,
  }
}

async function saveUpload(confirm = false) {
  if (!uploadForm.value?.file) {
    notice.value = 'Choose a file.'
    return
  }

  saving.value = true
  notice.value = ''
  const body = new FormData()
  body.append('file', uploadForm.value.file)
  body.append('document_type_id', String(uploadForm.value.document_type_id))
  body.append('title', uploadForm.value.title)
  body.append('attested_by', 'none')

  if (uploadForm.value.issue_date) {
    body.append('issue_date', uploadForm.value.issue_date)
  }

  if (uploadForm.value.expiry_date) {
    body.append('expiry_date', uploadForm.value.expiry_date)
  }

  if (confirm) {
    body.append('confirm_warnings', '1')
    body.append('warning_reason', warningReason.value)
  }

  await ensureCsrf()
  const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] || '')
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/applications/${route.params.id}/checklist-items/${uploadItem.value.id}/documents`, {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
    body,
  })
  const payload = await response.json().catch(() => null)
  saving.value = false

  if (response.status === 409) {
    warnings.value = payload?.warnings || []
    return
  }

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The file could not be uploaded.'
    return
  }

  detail.value = payload.data
  uploadItem.value = null
  warnings.value = []
  notice.value = 'Document uploaded.'
}

async function openFile(file) {
  const { response, payload } = await api(`/api/v1/documents/${file.id}/download`)

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The file could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank')
}

async function generateLetter() {
  saving.value = true
  notice.value = ''
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/deficiency-letters`, { method: 'POST', body: {} })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The letter could not be created.'
    return
  }

  detail.value = payload.data
  notice.value = 'Deficiency letter created.'
}

async function openLetter(letter) {
  const { response, payload } = await api(`/api/v1/deficiency-letters/${letter.id}/download`)

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The letter could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank')
}

async function resolveLetter(letter) {
  const { response, payload } = await api(`/api/v1/deficiency-letters/${letter.id}/resolve`, { method: 'POST', body: {} })

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The letter could not be resolved.'
    return
  }

  detail.value = payload.data
  notice.value = 'Letter marked resolved.'
}

async function closeApplication() {
  saving.value = true
  notice.value = ''
  const path = closeMode.value === 'reject' ? 'reject' : 'withdraw'
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/${path}`, {
    method: 'POST',
    body: { reason: closeReason.value },
  })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The application could not be closed.'
    return
  }

  detail.value = payload.data
  closeMode.value = ''
  closeReason.value = ''
  notice.value = path === 'reject' ? 'Application rejected.' : 'Application withdrawn.'
}

function openIssue() {
  issueWarnings.value = issuance.value?.warnings || []
  issueReason.value = ''
  showIssue.value = true
}

async function issueLicense() {
  saving.value = true
  notice.value = ''
  const body = {}

  if (issueWarnings.value.length > 0) {
    body.confirm_warnings = true
    body.warning_reason = issueReason.value
  }

  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/issue`, {
    method: 'POST',
    body,
  })
  saving.value = false

  if (response.status === 409) {
    issueWarnings.value = payload?.warnings || []
    return
  }

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The license could not be issued.'
    showIssue.value = false
    return
  }

  detail.value = payload.data
  showIssue.value = false
  notice.value = `License ${payload.data.issuance?.license_no || ''} issued.`
}

async function savePenalty() {
  saving.value = true
  notice.value = ''
  const body = {
    penalty_type: penaltyForm.value.penalty_type,
    basis: penaltyForm.value.basis,
    final_amount: Number(penaltyForm.value.final_amount),
    standard_amount: penaltyForm.value.standard_amount === '' ? null : Number(penaltyForm.value.standard_amount),
    waiver_reason: penaltyReduced.value ? penaltyForm.value.waiver_reason : null,
    waiver_order_no: penaltyReduced.value ? penaltyForm.value.waiver_order_no || null : null,
  }
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/penalties`, { method: 'POST', body })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The penalty could not be saved.'
    return
  }

  detail.value = payload.data
  showPenalty.value = false
  penaltyForm.value = { penalty_type: 'other', basis: '', standard_amount: '', final_amount: '', waiver_reason: '', waiver_order_no: '' }
  notice.value = 'Penalty recorded.'
}

async function approvePenalty(penalty) {
  saving.value = true
  notice.value = ''
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/penalties/${penalty.id}/approve-waiver`, { method: 'POST', body: {} })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The waiver could not be approved.'
    return
  }

  detail.value = payload.data
  notice.value = 'Waiver approved.'
}

async function saveChallan() {
  saving.value = true
  notice.value = ''
  const body = new FormData()
  body.append('challan_no', challanForm.value.challan_no)
  body.append('bank_name', challanForm.value.bank_name)
  body.append('payment_date', challanForm.value.payment_date)
  body.append('amount', String(challanForm.value.amount))

  if (challanForm.value.branch) {
    body.append('branch', challanForm.value.branch)
  }

  if (challanForm.value.file) {
    body.append('file', challanForm.value.file)
    body.append('document_type_id', String(challanForm.value.document_type_id))
    body.append('title', challanForm.value.title || 'Treasury challan')
  }

  await ensureCsrf()
  const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] || '')
  const response = await fetch(`${import.meta.env.VITE_API_URL}/api/v1/applications/${route.params.id}/challans`, {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
    body,
  })
  const payload = await response.json().catch(() => null)
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The challan could not be saved.'
    return
  }

  detail.value = payload.data
  showChallan.value = false
  challanForm.value = { challan_no: '', bank_name: '', branch: '', payment_date: '', amount: '', document_type_id: '', title: 'Treasury challan', file: null }
  notice.value = 'Challan recorded.'
}

async function verifyChallan(challan, status) {
  saving.value = true
  notice.value = ''
  const { response, payload } = await api(`/api/v1/challans/${challan.id}/verify`, {
    method: 'POST',
    body: {
      verification_status: status,
      remarks: status === 'rejected' ? challanRemarks.value : null,
    },
  })
  saving.value = false

  if (!response.ok) {
    notice.value = firstError(payload?.errors) || 'The challan could not be updated.'
    return
  }

  detail.value = payload.data
  rejectChallanId.value = null
  challanRemarks.value = ''
  notice.value = status === 'verified' ? 'Challan verified.' : 'Challan rejected.'
}

async function loadActivity() {
  tab.value = 'log'
  const { response, payload } = await api(`/api/v1/applications/${route.params.id}/activity`)
  activity.value = response.ok ? payload.data || [] : []
}

onMounted(load)
</script>

<template>
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <template v-if="application">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">{{ application.application_no }}</h1>
          <p class="text-sm">{{ application.applicant_name }} · {{ application.application_type === 'renewal' ? 'Renewal' : 'New' }} · Via {{ application.submitted_via }}</p>
          <p class="text-muted-foreground text-sm">{{ statusLabel(application.status) }}<span v-if="application.late_days"> · {{ application.late_days }} days late</span></p>
        </div>
        <Button type="button" variant="outline" @click="router.push({ name: 'applications' })">Back</Button>
      </div>

      <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveHeader">
        <div class="grid gap-1">
          <Label for="diary">Diary no</Label>
          <Input id="diary" v-model="header.diary_no" aria-label="Diary no" :disabled="!canHeader || closed" />
        </div>
        <div class="grid gap-1">
          <Label for="received">Received</Label>
          <Input id="received" v-model="header.received_at" type="date" aria-label="Received" :disabled="!canHeader || closed" />
        </div>
        <div class="grid gap-1">
          <Label for="pages">Total pages</Label>
          <Input id="pages" v-model="header.total_pages" type="number" min="0" aria-label="Total pages" :disabled="!canHeader || closed" />
        </div>
        <Button v-if="canHeader && !closed" type="submit" variant="outline" :disabled="saving">Save</Button>
      </form>

      <ol class="flex flex-wrap gap-2 text-sm">
        <li v-for="stage in detail.stages" :key="stage.id" class="rounded-md border px-2 py-1" :class="stage.state === 'current' ? 'border-primary font-medium' : ''">
          {{ stage.sequence }}. {{ stage.name }}
          <span class="text-muted-foreground">{{ stage.state === 'inactive' ? '(off)' : stage.state }}</span>
        </li>
      </ol>

      <div v-if="currentStage && !closed" class="flex flex-wrap items-center gap-2">
        <p class="text-sm">Stage due: {{ application.stage_due_at ? displayDate(application.stage_due_at) : 'No SLA' }}</p>
        <Button v-if="canActOnStage && currentStage.code !== 'issuance'" type="button" :disabled="saving" @click="completeStage">Complete Stage</Button>
        <Button v-if="canActOnStage && currentStage.is_skippable" type="button" variant="outline" @click="showSkip = !showSkip">Skip</Button>
        <Button type="button" variant="outline" :disabled="!issueReady || saving" @click="openIssue">Issue License</Button>
      </div>
      <ul v-if="canIssue && issuance?.blockers?.length" class="text-sm">
        <li>Cannot issue yet:</li>
        <li v-for="blocker in issuance.blockers" :key="blocker">{{ blocker }}</li>
      </ul>
      <p v-if="canIssue && issuance && !issuance.enforcement" class="text-sm">Enforcement is off. A license can be issued and marked documents incomplete.</p>
      <form v-if="showSkip" class="flex flex-wrap items-end gap-2" @submit.prevent="skipStage">
        <div class="grid gap-1">
          <Label for="skip-remarks">Skip remarks</Label>
          <Input id="skip-remarks" v-model="skipRemarks" required aria-label="Skip remarks" />
        </div>
        <Button type="submit" :disabled="saving">Skip stage</Button>
      </form>

      <div class="flex flex-wrap gap-2 border-b pb-2">
        <button type="button" class="rounded-md px-3 py-1 text-sm" :class="tab === 'checklist' ? 'bg-muted' : ''" @click="tab = 'checklist'">Checklist</button>
        <button type="button" class="rounded-md px-3 py-1 text-sm" :class="tab === 'deficiency' ? 'bg-muted' : ''" @click="tab = 'deficiency'">Deficiency</button>
        <button type="button" class="rounded-md px-3 py-1 text-sm" :class="tab === 'fees' ? 'bg-muted' : ''" @click="tab = 'fees'">Fees</button>
        <button type="button" class="rounded-md px-3 py-1 text-sm" :class="tab === 'log' ? 'bg-muted' : ''" @click="loadActivity">Log</button>
      </div>

      <div v-if="tab === 'checklist'" class="grid gap-3">
        <Alert v-if="application.status === 'issued' && issuance?.documents_status === 'incomplete'">
          <AlertTitle>
            Documents incomplete — {{ issuance.outstanding_required }} required items outstanding. Checklist remains open until {{ displayDate(issuance.valid_to) }}.
          </AlertTitle>
        </Alert>
        <p class="text-sm">{{ application.checklist_name }} v{{ application.checklist_version }} · Progress {{ detail.checklist.done }}/{{ detail.checklist.total }}</p>
        <div class="overflow-x-auto rounded-md border">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
              <tr>
                <th class="px-3 py-2">Annex</th>
                <th class="px-3 py-2">Item</th>
                <th class="px-3 py-2">Pages</th>
                <th class="px-3 py-2">File</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in detail.checklist.items" :key="item.id" class="border-t">
                <td class="px-3 py-2">{{ item.annex_code }}</td>
                <td class="px-3 py-2">{{ item.title }}<span v-if="item.officer_remarks" class="text-muted-foreground block">{{ item.officer_remarks }}</span></td>
                <td class="px-3 py-2">
                  <input :value="item.page_count ?? ''" type="number" min="0" class="border-input h-9 w-20 rounded-md border px-2" aria-label="Pages" @change="savePages(item, $event)">
                </td>
                <td class="px-3 py-2">
                  <button v-for="file in item.files" :key="file.id" type="button" class="block underline" @click="openFile(file)">{{ file.original_name }}</button>
                  <span v-if="item.files.length === 0">—</span>
                </td>
                <td class="px-3 py-2">{{ statusLabel(item.status) }}</td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap gap-1">
                    <Button v-if="canProcess && checklistOpen" type="button" variant="outline" @click="setItem(item, 'verified')">Verify</Button>
                    <Button v-if="canProcess && checklistOpen" type="button" variant="outline" @click="startDeficient(item)">Deficient</Button>
                    <Button v-if="canProcess && checklistOpen" type="button" variant="outline" @click="setItem(item, 'not_applicable')">N/A</Button>
                    <Button v-if="canUpload && checklistOpen" type="button" variant="outline" @click="startUpload(item)">Upload</Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <form v-if="deficientItem" class="grid max-w-xl gap-3 rounded-md border p-3" @submit.prevent="saveDeficient">
          <h3 class="font-medium">Deficient · {{ deficientItem.annex_code }} {{ deficientItem.title }}</h3>
          <div class="grid gap-1">
            <Label for="deficient-remarks">Remarks</Label>
            <Input id="deficient-remarks" v-model="deficientRemarks" required aria-label="Deficiency remarks" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="deficientItem = null">Cancel</Button>
            <Button type="submit">Save</Button>
          </div>
        </form>

        <form v-if="uploadItem" class="grid max-w-xl gap-3 rounded-md border p-3" @submit.prevent="saveUpload(false)">
          <h3 class="font-medium">Upload · {{ uploadItem.annex_code }} {{ uploadItem.title }}</h3>
          <p v-if="uploadItem.requires_validity_dates" class="text-sm">This item needs an issue date and an expiry date.</p>
          <Alert v-for="warning in warnings" :key="warning" variant="destructive">
            <AlertTitle>{{ warning }}</AlertTitle>
          </Alert>
          <div class="grid gap-1">
            <Label for="upload-file">File</Label>
            <input id="upload-file" type="file" aria-label="File" @change="uploadForm.file = $event.target.files?.[0] || null">
          </div>
          <div class="grid gap-1">
            <Label for="upload-type">Type</Label>
            <select id="upload-type" v-model="uploadForm.document_type_id" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" required aria-label="Type">
              <option value="">Choose</option>
              <option v-for="type in detail.document_types" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <Label for="upload-title">Title</Label>
            <Input id="upload-title" v-model="uploadForm.title" required aria-label="Title" />
          </div>
          <div v-if="uploadItem.requires_validity_dates" class="grid gap-3 sm:grid-cols-2">
            <div class="grid gap-1">
              <Label for="upload-issue">Issue date</Label>
              <Input id="upload-issue" v-model="uploadForm.issue_date" type="date" aria-label="Issue date" />
            </div>
            <div class="grid gap-1">
              <Label for="upload-expiry">Expiry date</Label>
              <Input id="upload-expiry" v-model="uploadForm.expiry_date" type="date" aria-label="Expiry date" />
            </div>
          </div>
          <div v-if="warnings.length" class="grid gap-1">
            <Label for="upload-reason">Warning reason</Label>
            <Input id="upload-reason" v-model="warningReason" aria-label="Warning reason" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="uploadItem = null">Cancel</Button>
            <Button v-if="warnings.length" type="button" :disabled="saving" @click="saveUpload(true)">Confirm and save</Button>
            <Button v-else type="submit" :disabled="saving">Save</Button>
          </div>
        </form>
      </div>

      <div v-else-if="tab === 'deficiency'" class="grid gap-3">
        <Button v-if="canProcess && currentStage?.code === 'deficiency' && !closed" type="button" :disabled="saving" @click="generateLetter">Generate deficiency letter</Button>
        <div class="overflow-x-auto rounded-md border">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
              <tr>
                <th class="px-3 py-2">Letter no</th>
                <th class="px-3 py-2">Issued</th>
                <th class="px-3 py-2">Reply due</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="letter in detail.letters" :key="letter.id" class="border-t">
                <td class="px-3 py-2">{{ letter.letter_no }}</td>
                <td class="px-3 py-2">{{ displayDate(letter.issued_at) }}</td>
                <td class="px-3 py-2">{{ displayDate(letter.reply_due_date) }}</td>
                <td class="px-3 py-2">{{ statusLabel(letter.status) }}</td>
                <td class="px-3 py-2">
                  <div class="flex gap-1">
                    <Button type="button" variant="outline" @click="openLetter(letter)">PDF</Button>
                    <Button v-if="canProcess && letter.status === 'open'" type="button" variant="outline" @click="resolveLetter(letter)">Mark resolved</Button>
                  </div>
                </td>
              </tr>
              <tr v-if="detail.letters.length === 0">
                <td class="text-muted-foreground px-3 py-4" colspan="5">No deficiency letters.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="tab === 'fees'" class="grid gap-4">
        <div class="grid gap-1 text-sm">
          <h2 class="font-medium">Fee</h2>
          <p v-if="detail.fees.quote">{{ detail.fees.quote.label }} (effective {{ displayDate(detail.fees.quote.effective_from) }}) {{ rupees(detail.fees.quote.amount) }}</p>
          <p v-else>Fee is not configured for this date. Contact the system administrator.</p>
          <p v-if="detail.fees.quote && !detail.fees.recorded" class="text-muted-foreground">This amount is recorded when the fee stage is completed.</p>
        </div>

        <div class="grid gap-2">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-medium">Penalties</h2>
            <Button v-if="canEnterPenalty && !closed" type="button" variant="outline" @click="showPenalty = !showPenalty">Add penalty</Button>
          </div>
          <p v-for="helper in detail.fees.helpers" :key="helper.penalty_type" class="text-sm">{{ helper.text }}</p>
          <form v-if="showPenalty" class="grid gap-2 rounded-md border p-3" @submit.prevent="savePenalty">
            <div class="grid gap-1">
              <Label for="penalty-type">Type</Label>
              <select id="penalty-type" v-model="penaltyForm.penalty_type" class="border-input bg-background h-9 rounded-md border px-2 text-sm" aria-label="Type">
                <option value="late_renewal">Late renewal</option>
                <option value="no_technical_staff">No technical staff</option>
                <option value="restoration">Restoration</option>
                <option value="other">Other</option>
              </select>
            </div>
            <p v-if="selectedHelper" class="text-sm">{{ selectedHelper.text }}</p>
            <p v-if="selectedRate" class="text-muted-foreground text-sm">Reference rate {{ rupees(selectedRate.amount) }}/{{ selectedRate.unit }}.</p>
            <div class="grid gap-1">
              <Label for="penalty-basis">Basis</Label>
              <Input id="penalty-basis" v-model="penaltyForm.basis" required aria-label="Basis" />
            </div>
            <div class="flex flex-wrap gap-2">
              <div class="grid gap-1">
                <Label for="standard-amount">Standard amount</Label>
                <Input id="standard-amount" v-model="penaltyForm.standard_amount" type="number" min="0" step="0.01" aria-label="Standard amount" />
              </div>
              <div class="grid gap-1">
                <Label for="final-amount">Final amount</Label>
                <Input id="final-amount" v-model="penaltyForm.final_amount" type="number" min="0" step="0.01" required aria-label="Final amount" />
              </div>
            </div>
            <div v-if="penaltyReduced" class="flex flex-wrap gap-2">
              <div class="grid gap-1">
                <Label for="waiver-reason">Waiver reason</Label>
                <Input id="waiver-reason" v-model="penaltyForm.waiver_reason" required aria-label="Waiver reason" />
              </div>
              <div class="grid gap-1">
                <Label for="waiver-order">Order no</Label>
                <Input id="waiver-order" v-model="penaltyForm.waiver_order_no" aria-label="Order no" />
              </div>
            </div>
            <div class="flex gap-2">
              <Button type="submit" :disabled="saving">Save penalty</Button>
              <Button type="button" variant="outline" @click="showPenalty = false">Cancel</Button>
            </div>
          </form>
          <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
              <thead class="bg-muted/50 text-left">
                <tr>
                  <th class="px-3 py-2">Type</th>
                  <th class="px-3 py-2">Basis</th>
                  <th class="px-3 py-2">Standard</th>
                  <th class="px-3 py-2">Final</th>
                  <th class="px-3 py-2">Waiver</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="penalty in detail.fees.penalties" :key="penalty.id" class="border-t">
                  <td class="px-3 py-2">{{ penaltyLabel(penalty.penalty_type) }}</td>
                  <td class="px-3 py-2">{{ penalty.basis }}</td>
                  <td class="px-3 py-2">{{ penalty.standard_amount == null ? '—' : rupees(penalty.standard_amount) }}</td>
                  <td class="px-3 py-2">{{ rupees(penalty.final_amount) }}</td>
                  <td class="px-3 py-2">
                    <span v-if="!penalty.is_waived_or_reduced">—</span>
                    <span v-else-if="penalty.waiver_approved">Approved by {{ penalty.waiver_approved_by }}</span>
                    <span v-else class="inline-flex items-center gap-2">
                      Pending
                      <Button v-if="canWaive" type="button" variant="outline" :disabled="saving" @click="approvePenalty(penalty)">Approve</Button>
                    </span>
                  </td>
                </tr>
                <tr v-if="detail.fees.penalties.length === 0">
                  <td class="text-muted-foreground px-3 py-4" colspan="5">No penalties entered.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p class="text-sm font-medium">Total payable {{ rupees(detail.fees.total_payable) }}</p>

        <div class="grid gap-2">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-medium">Challans</h2>
            <Button v-if="canAddChallan && !closed" type="button" variant="outline" @click="showChallan = !showChallan">Add challan</Button>
          </div>
          <form v-if="showChallan" class="grid gap-2 rounded-md border p-3" @submit.prevent="saveChallan">
            <div class="flex flex-wrap gap-2">
              <div class="grid gap-1">
                <Label for="challan-no">Challan no</Label>
                <Input id="challan-no" v-model="challanForm.challan_no" required aria-label="Challan no" />
              </div>
              <div class="grid gap-1">
                <Label for="bank-name">Bank</Label>
                <Input id="bank-name" v-model="challanForm.bank_name" required aria-label="Bank" />
              </div>
              <div class="grid gap-1">
                <Label for="branch">Branch</Label>
                <Input id="branch" v-model="challanForm.branch" aria-label="Branch" />
              </div>
              <div class="grid gap-1">
                <Label for="payment-date">Date</Label>
                <Input id="payment-date" v-model="challanForm.payment_date" type="date" required aria-label="Date" />
              </div>
              <div class="grid gap-1">
                <Label for="challan-amount">Amount</Label>
                <Input id="challan-amount" v-model="challanForm.amount" type="number" min="0.01" step="0.01" required aria-label="Amount" />
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <div class="grid gap-1">
                <Label for="challan-file">File</Label>
                <Input id="challan-file" type="file" aria-label="File" @change="challanForm.file = $event.target.files?.[0] || null" />
              </div>
              <div v-if="challanForm.file" class="grid gap-1">
                <Label for="challan-type">Document type</Label>
                <select id="challan-type" v-model="challanForm.document_type_id" class="border-input bg-background h-9 rounded-md border px-2 text-sm" required aria-label="Document type">
                  <option value="">Choose</option>
                  <option v-for="type in detail.document_types" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </div>
            </div>
            <div class="flex gap-2">
              <Button type="submit" :disabled="saving">Save challan</Button>
              <Button type="button" variant="outline" @click="showChallan = false">Cancel</Button>
            </div>
          </form>
          <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
              <thead class="bg-muted/50 text-left">
                <tr>
                  <th class="px-3 py-2">Challan no</th>
                  <th class="px-3 py-2">Bank</th>
                  <th class="px-3 py-2">Date</th>
                  <th class="px-3 py-2">Amount</th>
                  <th class="px-3 py-2">File</th>
                  <th class="px-3 py-2">Status</th>
                  <th class="px-3 py-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="challan in detail.fees.challans" :key="challan.id" class="border-t">
                  <td class="px-3 py-2">{{ challan.challan_no }}</td>
                  <td class="px-3 py-2">{{ challan.bank_name }}</td>
                  <td class="px-3 py-2">{{ displayDate(challan.payment_date) }}</td>
                  <td class="px-3 py-2">{{ rupees(challan.amount) }}</td>
                  <td class="px-3 py-2">
                    <Button v-if="challan.document" type="button" variant="outline" @click="openFile(challan.document)">PDF</Button>
                    <span v-else>—</span>
                  </td>
                  <td class="px-3 py-2">{{ statusLabel(challan.verification_status) }}</td>
                  <td class="px-3 py-2">
                    <div v-if="canVerifyChallan && challan.verification_status === 'pending' && !closed" class="flex gap-1">
                      <Button type="button" variant="outline" :disabled="saving" @click="verifyChallan(challan, 'verified')">Verify</Button>
                      <Button type="button" variant="outline" @click="rejectChallanId = challan.id">Reject</Button>
                    </div>
                  </td>
                </tr>
                <tr v-if="detail.fees.challans.length === 0">
                  <td class="text-muted-foreground px-3 py-4" colspan="7">No challans.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <form v-if="rejectChallanId" class="flex flex-wrap items-end gap-2" @submit.prevent="verifyChallan(detail.fees.challans.find((row) => row.id === rejectChallanId), 'rejected')">
            <div class="grid gap-1">
              <Label for="challan-remarks">Rejection remarks</Label>
              <Input id="challan-remarks" v-model="challanRemarks" required aria-label="Rejection remarks" />
            </div>
            <Button type="submit" :disabled="saving">Confirm reject</Button>
            <Button type="button" variant="outline" @click="rejectChallanId = null">Cancel</Button>
          </form>
          <p class="text-sm">Paid (verified) {{ rupees(detail.fees.paid_verified) }} · Balance {{ rupees(detail.fees.balance) }}</p>
        </div>
      </div>

      <div v-else class="overflow-x-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="bg-muted/50 text-left">
            <tr>
              <th class="px-3 py-2">When</th>
              <th class="px-3 py-2">Action</th>
              <th class="px-3 py-2">Description</th>
              <th class="px-3 py-2">User</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in activity" :key="row.id" class="border-t">
              <td class="px-3 py-2">{{ displayDate(row.created_at) }}</td>
              <td class="px-3 py-2">{{ row.action }}</td>
              <td class="px-3 py-2">{{ row.description }}</td>
              <td class="px-3 py-2">{{ row.user_name || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!closed" class="flex flex-wrap gap-2 border-t pt-3">
        <Button v-if="canReject" type="button" variant="outline" @click="closeMode = 'reject'">Reject application</Button>
        <Button v-if="canProcess" type="button" variant="outline" @click="closeMode = 'withdraw'">Withdraw</Button>
        <Button type="button" variant="outline" :disabled="!issueReady || saving" @click="openIssue">Issue License</Button>
      </div>
      <form v-if="showIssue && issuance" class="grid max-w-xl gap-2 rounded-md border p-3" @submit.prevent="issueLicense">
        <h2 class="font-medium">Issue license — {{ application.applicant_name }}</h2>
        <p class="text-sm">License No: {{ issuance.license_no }} (auto)</p>
        <p class="text-sm">Valid from: {{ displayDate(issuance.valid_from) }}</p>
        <p class="text-sm">Valid to: {{ displayDate(issuance.valid_to) }}</p>
        <p class="text-sm">Products approved: {{ issuance.product_count ?? 0 }}</p>
        <p class="text-sm">Documents: {{ issuance.documents_status === 'incomplete' ? 'Incomplete' : 'Complete' }}<span v-if="issuance.outstanding_required"> ({{ issuance.outstanding_required }} items)</span></p>
        <p v-if="issuance.documents_status === 'incomplete'" class="text-sm">License will be flagged documents incomplete until all are verified.</p>
        <ul v-if="issueWarnings.length" class="text-sm">
          <li v-for="warning in issueWarnings" :key="warning">{{ warning }}</li>
        </ul>
        <div v-if="issueWarnings.length" class="grid gap-1">
          <Label for="issue-reason">Reason</Label>
          <Input id="issue-reason" v-model="issueReason" required minlength="3" aria-label="Reason" />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Issue</Button>
          <Button type="button" variant="outline" @click="showIssue = false">Cancel</Button>
        </div>
      </form>
      <form v-if="closeMode" class="flex flex-wrap items-end gap-2" @submit.prevent="closeApplication">
        <div class="grid gap-1">
          <Label for="close-reason">Reason</Label>
          <Input id="close-reason" v-model="closeReason" required aria-label="Reason" />
        </div>
        <Button type="submit" :disabled="saving">Confirm</Button>
        <Button type="button" variant="outline" @click="closeMode = ''">Cancel</Button>
      </form>
    </template>
  </div>
</template>
