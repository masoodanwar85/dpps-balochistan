<script setup>
import { onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError, upload } from '@/lib/api'

const rows = ref([])
const types = ref([])
const open = ref(false)
const replaceId = ref(null)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const form = ref({ title: '', document_type_id: '', attested_by: 'none', file: null })

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function statusValue(row) {
  return row.verification_status === 'verified' ? 'approved' : row.verification_status
}

function statusLabel(row) {
  if (row.verification_status === 'rejected') {
    return `Rejected: ${row.rejection_reason || ''}`
  }

  return row.verification_status === 'verified' ? 'Verified' : 'Pending'
}

async function load() {
  const { response, payload } = await api('/api/v1/portal/documents')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Documents could not be loaded.'
    return
  }

  rows.value = payload.data || []
  types.value = payload.meta?.document_types || []
}

async function save() {
  formError.value = ''
  const body = new FormData()
  body.append('title', form.value.title)
  body.append('document_type_id', form.value.document_type_id)
  body.append('attested_by', form.value.attested_by)
  body.append('file', form.value.file)
  const path = replaceId.value
    ? `/api/v1/portal/documents/${replaceId.value}/replace`
    : '/api/v1/portal/documents'
  const { response, payload } = await upload(path, body)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || (payload?.warnings || []).join(' ') || 'The document could not be saved.'
    return
  }

  open.value = false
  replaceId.value = null
  notice.value = 'Document submitted. The Directorate will verify this file.'
  await load()
}

async function openFile(row) {
  const { response, payload } = await api(`/api/v1/documents/${row.id}/download-url`)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The file could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank', 'noopener')
}

function onFile(event) {
  form.value.file = event.target.files?.[0] || null
}

onMounted(load)
</script>

<template>
  <div class="grid gap-6">
    <PageSection accent="rose" eyebrow="Company portal" title="Documents">
      <template #actions>
        <Button type="button" size="sm" @click="replaceId = null; open = true">Upload</Button>
      </template>
      <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
      <p v-if="notice" class="text-sm">{{ notice }}</p>
    </PageSection>

    <PageSection v-if="open" accent="violet" eyebrow="Documents" :title="replaceId ? 'Replace document' : 'Upload document'">
      <form class="grid max-w-lg gap-3" @submit.prevent="save">
        <div class="dpps-field">
          <Label for="title">Title</Label>
          <Input id="title" v-model="form.title" required />
        </div>
        <div class="dpps-field">
          <Label for="type">Type</Label>
          <select id="type" v-model="form.document_type_id" class="dpps-select" required>
            <option value="">Choose</option>
            <option v-for="row in types" :key="row.id" :value="row.id">{{ row.name }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="file">File</Label>
          <input id="file" type="file" required @change="onFile">
        </div>
        <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
        <div class="flex gap-2">
          <Button type="submit">Submit</Button>
          <Button type="button" variant="outline" @click="open = false">Cancel</Button>
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
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.title }}</td>
              <td>{{ row.type_name }}</td>
              <td class="tabular-nums">{{ displayDate(row.expiry_date) }}</td>
              <td>{{ row.version_no }}</td>
              <td><StatusBadge :value="statusValue(row)" :label="statusLabel(row)" /></td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" @click="openFile(row)">Open</Button>
                  <Button type="button" variant="outline" size="sm" @click="replaceId = row.id; open = true">Replace</Button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground" colspan="6">No documents.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>
  </div>
</template>
