<script setup>
import { computed, onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError, upload } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

function batchStatus(batch) {
  if (batch.committed) return 'Imported'
  if (batch.status === 'failed') return 'Failed'
  return 'Dry run'
}

const auth = useAuthStore()
const canRun = computed(() => auth.can('imports.run'))
const canResolve = computed(() => auth.can('imports.resolve'))

const batches = ref([])
const selected = ref(null)
const loadError = ref('')
const formError = ref('')
const file = ref(null)
const importType = ref('companies')
const issue = ref('')
const status = ref('open')
const savingId = ref(null)

const issueTypes = [
  ['', 'All'],
  ['bad_date', 'bad_date'],
  ['possible_duplicate', 'possible_duplicate'],
  ['missing_cnic', 'missing_cnic'],
  ['invalid_cnic', 'invalid_cnic'],
  ['unknown_district', 'unknown_district'],
  ['unparsed_product', 'unparsed_product'],
  ['missing_required', 'missing_required'],
  ['other', 'other'],
]

const statuses = [
  ['', 'All'],
  ['open', 'Open'],
  ['fixed', 'Fixed'],
  ['merged', 'Merged'],
  ['skipped', 'Skipped'],
]

async function loadBatches() {
  loadError.value = ''
  const { response, payload } = await api('/api/v1/imports')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load imports.'

    return
  }

  batches.value = payload.data ?? []
}

async function openBatch(batch) {
  const params = new URLSearchParams()

  if (issue.value) {
    params.set('issue', issue.value)
  }

  if (status.value) {
    params.set('status', status.value)
  }

  const query = params.toString()
  const { response, payload } = await api(`/api/v1/imports/${batch.id}${query ? `?${query}` : ''}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this import.'

    return
  }

  selected.value = {
    ...payload.data,
    exceptions: (payload.data.exceptions ?? []).map((row) => ({
      ...row,
      targetId: row.match_id ? String(row.match_id) : '',
      fieldValues: Object.fromEntries((row.fields_needed ?? []).map((field) => [field, ''])),
      comment: '',
    })),
  }
}

async function submit(mode) {
  formError.value = ''

  if (!file.value) {
    formError.value = 'Choose an Excel file.'

    return
  }

  const body = new FormData()
  body.append('file', file.value)
  body.append('type', importType.value)
  body.append('mode', mode)
  const { response, payload } = await upload('/api/v1/imports', body)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not read this workbook.'

    return
  }

  file.value = null
  const input = document.getElementById('import-file')

  if (input instanceof HTMLInputElement) {
    input.value = ''
  }

  await loadBatches()
  await openBatch(payload.data)
}

async function runBatch() {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/imports/${selected.value.id}/run`, { method: 'POST', body: {} })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not run this import.'

    return
  }

  await loadBatches()
  await openBatch(payload.data)
}

async function resolve(row, resolution) {
  formError.value = ''
  savingId.value = row.id
  const fields = {}

  for (const [field, value] of Object.entries(row.fieldValues ?? {})) {
    if (value !== '') {
      fields[field] = value
    }
  }

  const { response, payload } = await api(`/api/v1/import-exceptions/${row.id}`, {
    method: 'PUT',
    body: {
      resolution,
      target_id: resolution === 'merge' && row.targetId !== '' ? Number(row.targetId) : null,
      fields,
      comment: row.comment || null,
    },
  })
  savingId.value = null

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save this exception.'

    return
  }

  await loadBatches()
  await openBatch(selected.value)
}

function onFile(event) {
  file.value = event.target.files?.[0] ?? null
}

onMounted(loadBatches)
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError || formError" variant="destructive">
      <AlertTitle>{{ loadError || formError }}</AlertTitle>
    </Alert>

    <PageSection v-if="canRun" accent="amber" eyebrow="Imports" title="Upload workbook">
      <form class="flex flex-wrap items-end gap-2" @submit.prevent="submit('dry_run')">
        <div class="dpps-field">
          <Label for="import-type">Type</Label>
          <select id="import-type" v-model="importType" class="dpps-select" aria-label="Type">
            <option value="companies">Companies</option>
            <option value="dealers">Dealers</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="import-file">Upload Excel</Label>
          <Input id="import-file" type="file" accept=".xlsx" aria-label="Upload Excel" @change="onFile" />
        </div>
        <Button type="submit" variant="outline" size="sm">Dry Run</Button>
        <Button type="button" size="sm" @click="submit('run')">Run</Button>
        <p class="w-full text-sm text-muted-foreground">Run writes the rows when the workbook has no exceptions. A workbook with exceptions stays a dry run until those rows are skipped, fixed, merged, or accepted as new.</p>
      </form>
    </PageSection>

    <PageSection accent="slate" eyebrow="History" title="Import batches" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>File</th>
              <th>Rows</th>
              <th>Imported</th>
              <th>Exceptions</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="batches.length === 0">
              <td class="px-3 py-3 text-muted-foreground" colspan="5">No imports.</td>
            </tr>
            <tr v-for="batch in batches" :key="batch.id" class="cursor-pointer" @click="openBatch(batch)">
              <td class="font-medium">{{ batch.file_name }}</td>
              <td>{{ batch.total_rows }}</td>
              <td>{{ batch.committed ? batch.success_rows : '—' }}</td>
              <td>{{ batch.exception_rows }}</td>
              <td>
                <StatusBadge :value="batch.committed ? 'imported' : batch.status" :label="batchStatus(batch)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>

    <template v-if="selected">
      <PageSection accent="violet" eyebrow="Batch" :title="selected.file_name">
        <template #actions>
          <Button v-if="canRun" type="button" size="sm" :disabled="selected.committed || selected.open_exceptions > 0" @click="runBatch">Run</Button>
        </template>
        <p class="mb-4 text-sm text-muted-foreground">
          {{ selected.sheet_name }} · {{ selected.committed ? 'Imported' : 'Dry run' }} · {{ selected.open_exceptions }} open
        </p>
        <div class="flex flex-wrap items-end gap-2">
          <div class="dpps-field">
            <Label for="issue-filter">Issue</Label>
            <select id="issue-filter" v-model="issue" class="dpps-select" aria-label="Issue" @change="openBatch(selected)">
              <option v-for="[value, label] in issueTypes" :key="value || 'all-issues'" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="status-filter">Status</Label>
            <select id="status-filter" v-model="status" class="dpps-select" aria-label="Status" @change="openBatch(selected)">
              <option v-for="[value, label] in statuses" :key="value || 'all-statuses'" :value="value">{{ label }}</option>
            </select>
          </div>
        </div>
      </PageSection>

      <PageSection accent="slate" eyebrow="Exceptions" title="Rows to review" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Row</th>
                <th>Issue</th>
                <th>Details</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="selected.exceptions.length === 0">
                <td class="px-3 py-3 text-muted-foreground" colspan="4">No exceptions.</td>
              </tr>
              <tr v-for="row in selected.exceptions" :key="row.id" class="align-top">
                <td>{{ row.row_no }}</td>
                <td>{{ row.issue_type }}</td>
                <td>{{ row.issue_details }}</td>
                <td>
                  <p v-if="row.resolution_status !== 'open'" class="text-muted-foreground">{{ row.decision || row.resolution_status }}</p>
                  <div v-else-if="canResolve" class="grid gap-2">
                    <Input v-if="row.issue_type === 'possible_duplicate'" v-model="row.targetId" placeholder="Existing record id" aria-label="Existing record id" />
                    <Input v-for="field in row.fields_needed" :key="field" v-model="row.fieldValues[field]" :placeholder="field" :aria-label="field" />
                    <div class="flex flex-wrap gap-2">
                      <Button type="button" variant="outline" size="sm" :disabled="savingId === row.id" @click="resolve(row, 'skip')">Skip</Button>
                      <Button v-if="row.issue_type === 'possible_duplicate'" type="button" variant="outline" size="sm" :disabled="savingId === row.id" @click="resolve(row, 'merge')">Merge</Button>
                      <Button v-if="row.issue_type === 'possible_duplicate'" type="button" variant="outline" size="sm" :disabled="savingId === row.id" @click="resolve(row, 'new')">New</Button>
                      <Button v-if="row.fields_needed.length" type="button" size="sm" :disabled="savingId === row.id" @click="resolve(row, 'fix')">{{ row.issue_type === 'missing_cnic' || row.issue_type === 'invalid_cnic' ? 'Add CNIC' : 'Fix' }}</Button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
    </template>
  </div>
</template>
