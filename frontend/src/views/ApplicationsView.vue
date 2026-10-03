<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const canCreate = computed(() => auth.can('applications.create'))

const rows = ref([])
const meta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1, stages: [], districts: [] })
const entity = ref('')
const applicationType = ref('')
const stage = ref('')
const status = ref('open')
const slaBreached = ref(false)
const districtId = ref('')
const page = ref(1)
const loadError = ref('')
const mode = ref('list')
const form = ref(null)
const applicants = ref([])
const applicantSearch = ref('')
const formError = ref('')
const saving = ref(false)

const statuses = [
  ['open', 'Open'],
  ['all', 'All'],
  ['submitted', 'Submitted'],
  ['under_review', 'Under review'],
  ['deficiency_issued', 'Deficiency issued'],
  ['fee_pending', 'Fee pending'],
  ['ready_to_issue', 'Ready to issue'],
  ['issued', 'Issued'],
  ['rejected', 'Rejected'],
  ['withdrawn', 'Withdrawn'],
]

function statusLabel(value) {
  return statuses.find(([key]) => key === value)?.[1] || value
}

function stageLabel(row) {
  if (!row.current_stage) {
    return '—'
  }

  return `${row.current_stage.sequence}. ${row.current_stage.name}`
}

async function load() {
  loadError.value = ''
  const params = new URLSearchParams({ page: String(page.value), per_page: '25', 'filter[status]': status.value })

  if (entity.value) {
    params.set('filter[licensable_type]', entity.value)
  }

  if (applicationType.value) {
    params.set('filter[application_type]', applicationType.value)
  }

  if (stage.value) {
    params.set('filter[stage]', stage.value)
  }

  if (districtId.value) {
    params.set('filter[district_id]', districtId.value)
  }

  if (slaBreached.value) {
    params.set('filter[sla_breached]', '1')
  }

  const { response, payload } = await api(`/api/v1/applications?${params}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The list could not be loaded.'
    return
  }

  rows.value = payload.data || []
  meta.value = payload.meta || meta.value
}

function startNew(prefill = null) {
  formError.value = ''
  applicantSearch.value = ''
  applicants.value = []
  form.value = {
    licensable_type: prefill?.type || 'company',
    licensable_id: prefill?.id ? String(prefill.id) : '',
    application_type: 'new',
    diary_no: '',
    received_at: '',
  }
  mode.value = 'form'
  loadApplicants()
}

async function loadApplicants() {
  if (!form.value) {
    return
  }

  const path = form.value.licensable_type === 'dealer' ? '/api/v1/dealers' : '/api/v1/companies'
  const params = new URLSearchParams({ per_page: '100' })

  if (applicantSearch.value.trim()) {
    params.set('search', applicantSearch.value.trim())
  }

  const { response, payload } = await api(`${path}?${params}`)
  applicants.value = response.ok ? payload.data || [] : []
}

function applicantLabel(row) {
  if (form.value?.licensable_type === 'dealer') {
    return `${row.shop_name} (${row.dealer_code})`
  }

  return `${row.name} (${row.company_code})`
}

async function save() {
  saving.value = true
  formError.value = ''
  const { response, payload } = await api('/api/v1/applications', {
    method: 'POST',
    body: {
      licensable_type: form.value.licensable_type,
      licensable_id: Number(form.value.licensable_id),
      application_type: form.value.application_type,
      diary_no: form.value.diary_no || null,
      received_at: form.value.received_at || null,
    },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The application could not be saved.'
    return
  }

  router.push({ name: 'application-detail', params: { id: payload.data.application.id } })
}

function applyListQuery() {
  if (typeof route.query.type === 'string') {
    applicationType.value = route.query.type
  }

  if (typeof route.query.status === 'string') {
    status.value = route.query.status
  }

  slaBreached.value = route.query.sla === 'breached'
}

watch(() => [route.query.type, route.query.status, route.query.sla], () => {
  applyListQuery()
  page.value = 1
  load()
})

onMounted(async () => {
  applyListQuery()
  await load()

  if (canCreate.value && route.query.new && route.query.id) {
    startNew({ type: String(route.query.new), id: String(route.query.id) })
  }
})
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <template v-if="mode === 'list'">
      <PageSection accent="amber" eyebrow="Workflow" title="Applications">
        <template #actions>
          <Button v-if="canCreate" type="button" size="sm" @click="startNew()">New Application</Button>
        </template>
        <div class="flex flex-wrap items-end gap-3">
          <div class="dpps-field">
            <Label for="app-entity">Applicant</Label>
            <select id="app-entity" v-model="entity" class="dpps-select" aria-label="Applicant" @change="page = 1; load()">
              <option value="">All</option>
              <option value="company">Company</option>
              <option value="dealer">Dealer</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="app-type">Type</Label>
            <select id="app-type" v-model="applicationType" class="dpps-select" aria-label="Type" @change="page = 1; load()">
              <option value="">All</option>
              <option value="new">New</option>
              <option value="renewal">Renewal</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="app-stage">Stage</Label>
            <select id="app-stage" v-model="stage" class="dpps-select" aria-label="Stage" @change="page = 1; load()">
              <option value="">All</option>
              <option v-for="item in meta.stages || []" :key="item.code" :value="item.code">{{ item.name }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="app-status">Status</Label>
            <select id="app-status" v-model="status" class="dpps-select" aria-label="Status" @change="page = 1; load()">
              <option v-for="[value, label] in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="app-district">District</Label>
            <select id="app-district" v-model="districtId" class="dpps-select" aria-label="District" @change="page = 1; load()">
              <option value="">All</option>
              <option v-for="district in meta.districts || []" :key="district.id" :value="district.id">{{ district.name }}</option>
            </select>
          </div>
        </div>
      </PageSection>

      <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>App No</th>
                <th>Applicant</th>
                <th>Type</th>
                <th>Current Stage</th>
                <th>Day</th>
                <th>SLA</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="dpps-row-link" @click="router.push({ name: 'application-detail', params: { id: row.id } })">
                <td class="font-medium">{{ row.application_no }}</td>
                <td>{{ row.applicant_name }}</td>
                <td><StatusBadge :value="row.application_type" :label="row.application_type === 'renewal' ? 'Renewal' : 'New'" /></td>
                <td>{{ stageLabel(row) }}</td>
                <td class="tabular-nums">{{ row.days_in_stage }}/{{ row.sla_days ?? '—' }}</td>
                <td>
                  <StatusBadge
                    :value="row.sla_breached ? 'overdue' : 'on_time'"
                    :label="row.sla_breached ? 'Overdue' : 'On time'"
                  />
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td class="text-muted-foreground" colspan="6">No applications.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
      <div class="flex items-center justify-between text-sm">
        <p class="text-muted-foreground">Showing {{ meta.total }} · Status {{ statusLabel(status) }}</p>
        <div class="flex gap-2">
          <Button type="button" variant="outline" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
          <Button type="button" variant="outline" :disabled="page >= meta.last_page" @click="page += 1; load()">Next</Button>
        </div>
      </div>
    </template>

    <form v-else class="grid gap-6" @submit.prevent="save">
      <PageSection accent="amber" eyebrow="Workflow" title="New application">
        <Alert v-if="formError" variant="destructive" class="mb-4">
          <AlertTitle>{{ formError }}</AlertTitle>
        </Alert>
        <div class="grid max-w-xl gap-4">
          <div class="dpps-field">
            <Label for="form-entity">Applicant kind</Label>
            <select id="form-entity" v-model="form.licensable_type" class="dpps-select" required aria-label="Applicant kind" @change="form.licensable_id = ''; loadApplicants()">
              <option value="company">Company</option>
              <option value="dealer">Dealer</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="form-search">Find applicant</Label>
            <div class="flex gap-2">
              <Input id="form-search" v-model="applicantSearch" aria-label="Find applicant" @keyup.enter.prevent="loadApplicants" />
              <Button type="button" variant="outline" @click="loadApplicants">Search</Button>
            </div>
          </div>
          <div class="dpps-field">
            <Label for="form-applicant">Applicant</Label>
            <select id="form-applicant" v-model="form.licensable_id" class="dpps-select" required aria-label="Applicant">
              <option value="">Choose</option>
              <option v-for="row in applicants" :key="row.id" :value="String(row.id)">{{ applicantLabel(row) }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="form-kind">Type</Label>
            <select id="form-kind" v-model="form.application_type" class="dpps-select" required aria-label="Type">
              <option value="new">New</option>
              <option value="renewal">Renewal</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="form-diary">Diary no</Label>
            <Input id="form-diary" v-model="form.diary_no" aria-label="Diary no" />
          </div>
          <div class="dpps-field">
            <Label for="form-received">Received</Label>
            <Input id="form-received" v-model="form.received_at" type="date" aria-label="Received" />
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="mode = 'list'">Cancel</Button>
            <Button type="submit" :disabled="saving">Save</Button>
          </div>
        </div>
      </PageSection>
    </form>
  </div>
</template>
