<script setup>
import { onMounted, ref } from 'vue'
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
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Documents</h2>
      <Button type="button" @click="replaceId = null; open = true">Upload</Button>
    </div>
    <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
    <p v-if="notice" class="text-sm">{{ notice }}</p>
    <form v-if="open" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="save">
      <div class="grid gap-1">
        <Label for="title">Title</Label>
        <Input id="title" v-model="form.title" required />
      </div>
      <div class="grid gap-1">
        <Label for="type">Type</Label>
        <select id="type" v-model="form.document_type_id" class="border-input h-9 rounded-md border px-3 text-sm" required>
          <option value="">Choose</option>
          <option v-for="row in types" :key="row.id" :value="row.id">{{ row.name }}</option>
        </select>
      </div>
      <div class="grid gap-1">
        <Label for="file">File</Label>
        <input id="file" type="file" required @change="onFile">
      </div>
      <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
      <Button type="submit">Submit</Button>
    </form>
    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Title</th>
            <th class="px-3 py-2">Type</th>
            <th class="px-3 py-2">Expiry</th>
            <th class="px-3 py-2">Version</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.title }}</td>
            <td class="px-3 py-2">{{ row.type_name }}</td>
            <td class="px-3 py-2">{{ displayDate(row.expiry_date) }}</td>
            <td class="px-3 py-2">{{ row.version_no }}</td>
            <td class="px-3 py-2">{{ statusLabel(row) }}</td>
            <td class="px-3 py-2">
              <Button type="button" variant="outline" @click="openFile(row)">Open</Button>
              <Button type="button" variant="outline" class="ml-2" @click="replaceId = row.id; open = true">Replace</Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
