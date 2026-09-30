<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const families = ref([])
const template = ref(null)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const saving = ref(false)
const showForm = ref(false)
const showPreview = ref(false)
const editingId = ref(null)
const copySourceId = ref('')
const dragIndex = ref(null)

const attestations = [
  { value: 'none', label: 'None' },
  { value: 'gazetted_officer', label: 'Gazetted officer' },
  { value: 'notary_public', label: 'Notary public' },
  { value: 'oath_commissioner', label: 'Oath commissioner' },
]

const form = reactive(blankItem())

const copySources = computed(() => families.value.flatMap((family) => {
  if (!family.published || !template.value) {
    return []
  }

  if (family.entity_type === template.value.entity_type && family.application_type === template.value.application_type) {
    return []
  }

  return [{
    id: family.published.id,
    label: `${family.name} v${family.published.version_no}`,
  }]
}))

function blankItem() {
  return {
    annex_code: '',
    title: '',
    description: '',
    form_reference: '',
    is_required: true,
    requires_upload: true,
    allowed_file_types: 'pdf,jpg,jpeg,png',
    max_files: 5,
    attestation_required: 'none',
    requires_validity_dates: false,
    must_cover_license_period: false,
    portal_uploadable: true,
  }
}

function attestLabel(value) {
  if (value === 'gazetted_officer') {
    return 'Gaz.'
  }

  if (value === 'notary_public') {
    return 'Notary'
  }

  if (value === 'oath_commissioner') {
    return 'Oath'
  }

  return '—'
}

function mark(value) {
  return value ? '✓' : '—'
}

async function loadFamilies() {
  const { response, payload } = await api('/api/v1/checklist-templates')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load checklists.'

    return
  }

  families.value = payload.data
}

async function loadTemplate(id) {
  loadError.value = ''
  const { response, payload } = await api(`/api/v1/checklist-templates/${id}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not open this checklist.'

    return
  }

  template.value = payload.data
  showForm.value = false
  showPreview.value = false
  formError.value = ''
}

function resetForm() {
  Object.assign(form, blankItem())
}

function startCreate() {
  resetForm()
  editingId.value = null
  showForm.value = true
  formError.value = ''
}

function startEdit(item) {
  Object.assign(form, {
    ...item,
    description: item.description || '',
    form_reference: item.form_reference || '',
  })
  editingId.value = item.id
  showForm.value = true
  formError.value = ''
}

function itemBody() {
  return {
    ...form,
    description: form.description || null,
    form_reference: form.form_reference || null,
    max_files: Number(form.max_files),
  }
}

async function saveItem() {
  saving.value = true
  formError.value = ''
  const creating = editingId.value === null
  const path = creating
    ? `/api/v1/checklist-templates/${template.value.id}/items`
    : `/api/v1/checklist-templates/${template.value.id}/items/${editingId.value}`
  const { response, payload } = await api(path, {
    method: creating ? 'POST' : 'PUT',
    body: itemBody(),
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save the item.'

    return
  }

  notice.value = creating ? 'Item added.' : 'Item saved.'
  showForm.value = false
  await loadTemplate(template.value.id)
  await loadFamilies()
}

async function removeItem(item) {
  if (!window.confirm(`Remove item ${item.annex_code}?`)) {
    return
  }

  const { response, payload } = await api(
    `/api/v1/checklist-templates/${template.value.id}/items/${item.id}`,
    { method: 'DELETE' },
  )

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not remove the item.'

    return
  }

  notice.value = 'Item removed.'
  await loadTemplate(template.value.id)
  await loadFamilies()
}

async function startVersion(id) {
  saving.value = true
  formError.value = ''
  const { response, payload } = await api(`/api/v1/checklist-templates/${id}/new-version`, { method: 'POST' })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not create a new version.'

    return
  }

  notice.value = 'Draft created. Publishing affects new applications only.'
  template.value = payload.data
  await loadFamilies()
}

async function publish() {
  if (!window.confirm(`Publish v${template.value.version_no}? This affects new applications only.`)) {
    return
  }

  saving.value = true
  const { response, payload } = await api(`/api/v1/checklist-templates/${template.value.id}/publish`, { method: 'POST' })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not publish.'

    return
  }

  notice.value = 'Published. Existing applications keep their checklist version.'
  template.value = payload.data
  await loadFamilies()
}

async function copyFrom() {
  if (!copySourceId.value) {
    return
  }

  saving.value = true
  const { response, payload } = await api(`/api/v1/checklist-templates/${template.value.id}/copy-from`, {
    method: 'POST',
    body: { source_template_id: Number(copySourceId.value) },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not copy items.'

    return
  }

  notice.value = 'Items copied.'
  template.value = payload.data
  copySourceId.value = ''
  await loadFamilies()
}

function onDrop(index) {
  if (!template.value?.editable || dragIndex.value === null || dragIndex.value === index) {
    dragIndex.value = null

    return
  }

  const items = [...template.value.items]
  const [moved] = items.splice(dragIndex.value, 1)
  items.splice(index, 0, moved)
  template.value.items = items
  dragIndex.value = null
  saveOrder(items.map((item) => item.id))
}

async function saveOrder(itemIds) {
  const { response, payload } = await api(
    `/api/v1/checklist-templates/${template.value.id}/items/reorder`,
    { method: 'PUT', body: { item_ids: itemIds } },
  )

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not reorder items.'
    await loadTemplate(template.value.id)

    return
  }

  template.value = payload.data
  notice.value = 'Order saved.'
}

onMounted(loadFamilies)
</script>

<template>
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>
    <Alert v-if="formError" variant="destructive">
      <AlertTitle>{{ formError }}</AlertTitle>
    </Alert>

    <section v-if="!template" class="overflow-x-auto rounded-xl border bg-card">
      <table class="w-full text-left text-sm">
        <thead class="border-b text-muted-foreground">
          <tr>
            <th class="px-3 py-2 font-medium">Template</th>
            <th class="px-3 py-2 font-medium">Published</th>
            <th class="px-3 py-2 font-medium">Draft</th>
            <th class="px-3 py-2 font-medium">Items</th>
            <th class="px-3 py-2 font-medium" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="family in families" :key="`${family.entity_type}-${family.application_type}`" class="border-b last:border-0">
            <td class="px-3 py-2">{{ family.name }}</td>
            <td class="px-3 py-2">{{ family.published ? `v${family.published.version_no}` : '—' }}</td>
            <td class="px-3 py-2">{{ family.draft ? `v${family.draft.version_no}` : '—' }}</td>
            <td class="px-3 py-2">{{ family.published?.item_count ?? family.draft?.item_count ?? '—' }}</td>
            <td class="px-3 py-2">
              <button type="button" class="underline" @click="loadTemplate(family.draft?.id || family.published?.id)">Open</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-else class="grid gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <Button type="button" variant="outline" @click="template = null">Back</Button>
        <h2 class="text-lg font-semibold">{{ template.name }} v{{ template.version_no }} ({{ template.status }})</h2>
        <span v-if="template.status === 'draft' && template.published_version_no" class="text-sm text-muted-foreground">
          v{{ template.published_version_no }} published
        </span>
      </div>
      <p class="text-sm text-muted-foreground">Publishing affects new applications only. An application keeps the checklist version it was given.</p>

      <div v-if="template.editable" class="flex flex-wrap items-end gap-2">
        <Button type="button" @click="startCreate">Add Item</Button>
        <div class="grid gap-1">
          <Label for="copy-source">Copy from another template</Label>
          <select id="copy-source" v-model="copySourceId" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
            <option value="">Select</option>
            <option v-for="source in copySources" :key="source.id" :value="source.id">{{ source.label }}</option>
          </select>
        </div>
        <Button type="button" variant="outline" :disabled="!copySourceId || saving" @click="copyFrom">Copy</Button>
        <Button type="button" variant="outline" @click="showPreview = !showPreview">Preview</Button>
        <Button type="button" :disabled="saving" @click="publish">Publish v{{ template.version_no }}</Button>
      </div>
      <div v-else>
        <Button type="button" :disabled="saving" @click="startVersion(template.id)">New version</Button>
      </div>

      <ol v-if="showPreview" class="list-decimal space-y-1 pl-5 text-sm">
        <li v-for="item in template.items" :key="item.id">{{ item.annex_code }}. {{ item.title }}</li>
      </ol>

      <form v-if="showForm" class="grid max-w-3xl gap-3 rounded-xl border bg-card p-4" @submit.prevent="saveItem">
        <h3 class="font-medium">{{ editingId ? 'Edit item' : 'New item' }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
          <div class="grid gap-1">
            <Label for="annex">Annex</Label>
            <Input id="annex" v-model="form.annex_code" required maxlength="5" />
          </div>
          <div class="grid gap-1">
            <Label for="form-ref">Form reference</Label>
            <Input id="form-ref" v-model="form.form_reference" maxlength="30" />
          </div>
          <div class="grid gap-1 md:col-span-2">
            <Label for="title">Title</Label>
            <Input id="title" v-model="form.title" required />
          </div>
          <div class="grid gap-1 md:col-span-2">
            <Label for="description">Description</Label>
            <textarea id="description" v-model="form.description" class="border-input min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm" />
          </div>
          <div class="grid gap-1">
            <Label for="file-types">Allowed file types</Label>
            <Input id="file-types" v-model="form.allowed_file_types" required />
          </div>
          <div class="grid gap-1">
            <Label for="max-files">Max files</Label>
            <Input id="max-files" v-model="form.max_files" type="number" min="1" max="99" required />
          </div>
          <div class="grid gap-1">
            <Label for="attestation">Attestation</Label>
            <select id="attestation" v-model="form.attestation_required" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
              <option v-for="option in attestations" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
          <label class="flex items-center gap-2"><input v-model="form.is_required" type="checkbox"> Required</label>
          <label class="flex items-center gap-2"><input v-model="form.requires_upload" type="checkbox"> Upload</label>
          <label class="flex items-center gap-2"><input v-model="form.requires_validity_dates" type="checkbox"> Validity dates</label>
          <label class="flex items-center gap-2"><input v-model="form.must_cover_license_period" type="checkbox"> Covers license period</label>
          <label class="flex items-center gap-2"><input v-model="form.portal_uploadable" type="checkbox"> Portal upload</label>
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="showForm = false">Cancel</Button>
        </div>
      </form>

      <div class="overflow-x-auto rounded-xl border bg-card">
        <table class="w-full text-left text-sm">
          <thead class="border-b text-muted-foreground">
            <tr>
              <th class="px-3 py-2 font-medium" />
              <th class="px-3 py-2 font-medium">Annex</th>
              <th class="px-3 py-2 font-medium">Title</th>
              <th class="px-3 py-2 font-medium">Req</th>
              <th class="px-3 py-2 font-medium">Upload</th>
              <th class="px-3 py-2 font-medium">Attest</th>
              <th class="px-3 py-2 font-medium">Cover</th>
              <th class="px-3 py-2 font-medium">Portal</th>
              <th class="px-3 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, index) in template.items"
              :key="item.id"
              class="border-b last:border-0"
              :draggable="template.editable"
              @dragstart="dragIndex = index"
              @dragover.prevent
              @drop="onDrop(index)"
            >
              <td class="px-3 py-2">{{ template.editable ? '≡' : '' }}</td>
              <td class="px-3 py-2">{{ item.annex_code }}</td>
              <td class="px-3 py-2">{{ item.title }}</td>
              <td class="px-3 py-2">{{ mark(item.is_required) }}</td>
              <td class="px-3 py-2">{{ mark(item.requires_upload) }}</td>
              <td class="px-3 py-2">{{ attestLabel(item.attestation_required) }}</td>
              <td class="px-3 py-2">{{ mark(item.must_cover_license_period) }}</td>
              <td class="px-3 py-2">{{ mark(item.portal_uploadable) }}</td>
              <td class="px-3 py-2">
                <template v-if="template.editable">
                  <button type="button" class="underline" @click="startEdit(item)">Edit</button>
                  <button type="button" class="ml-3 underline" @click="removeItem(item)">Remove</button>
                </template>
                <span v-else>—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="template.editable" class="text-sm text-muted-foreground">Drag ≡ to reorder.</p>
    </section>
  </div>
</template>
