<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const props = defineProps({
  endpoint: { type: String, required: true },
  searchPlaceholder: { type: String, default: 'Search' },
  columns: { type: Array, required: true },
  fields: { type: Array, required: true },
  blank: { type: Object, required: true },
  extraFilters: { type: Array, default: () => [] },
})

const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const filters = reactive({
  search: '',
  is_active: '',
  page: 1,
  extra: {},
})
const form = reactive({})

function resetForm() {
  Object.keys(form).forEach((key) => {
    delete form[key]
  })
  Object.assign(form, JSON.parse(JSON.stringify(props.blank)))
}

function cell(row, column) {
  const value = row[column.key]

  if (column.boolean) {
    return value ? 'Yes' : 'No'
  }

  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return value
}

async function loadRows() {
  const params = new URLSearchParams({
    page: String(filters.page),
    per_page: '50',
    sort: 'name',
  })

  if (props.endpoint.endsWith('/products')) {
    params.set('sort', 'generic_name')
  }

  if (filters.search) {
    params.set('search', filters.search)
  }

  if (filters.is_active !== '') {
    params.set('filter[is_active]', filters.is_active)
  }

  for (const filter of props.extraFilters) {
    const value = filters.extra[filter.key]

    if (value) {
      params.set(`filter[${filter.key}]`, value)
    }
  }

  const { response, payload } = await api(`${props.endpoint}?${params}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load this list.'

    return
  }

  loadError.value = ''
  rows.value = payload.data
  meta.value = payload.meta
}

function applyFilters() {
  filters.page = 1
  loadRows()
}

function changePage(page) {
  filters.page = page
  loadRows()
}

function startCreate() {
  resetForm()
  editingId.value = null
  showForm.value = true
  formError.value = ''
  notice.value = ''
}

function startEdit(row) {
  resetForm()

  for (const field of props.fields) {
    form[field.key] = row[field.key]
  }

  editingId.value = row.id
  showForm.value = true
  formError.value = ''
  notice.value = ''
}

function bodyFromForm() {
  const body = {}

  for (const field of props.fields) {
    let value = form[field.key]

    if (field.integer && value !== '' && value !== null && value !== undefined) {
      value = Number(value)
    }

    body[field.key] = value
  }

  return body
}

async function save() {
  saving.value = true
  formError.value = ''
  const creating = editingId.value === null
  const path = creating ? props.endpoint : `${props.endpoint}/${editingId.value}`
  const { response, payload } = await api(path, {
    method: creating ? 'POST' : 'PUT',
    body: bodyFromForm(),
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save.'

    return
  }

  notice.value = creating ? 'Created.' : 'Saved.'
  showForm.value = false
  await loadRows()
}

onMounted(() => {
  for (const filter of props.extraFilters) {
    filters.extra[filter.key] = ''
  }

  resetForm()
  loadRows()
})
</script>

<template>
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <form class="grid gap-3 md:grid-cols-4" @submit.prevent="applyFilters">
      <div class="grid gap-1">
        <Label for="catalog-search">Search</Label>
        <Input id="catalog-search" v-model="filters.search" :placeholder="searchPlaceholder" />
      </div>
      <div class="grid gap-1">
        <Label for="catalog-active">Active</Label>
        <select id="catalog-active" v-model="filters.is_active" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
          <option value="">All</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>
      <div v-for="filter in extraFilters" :key="filter.key" class="grid gap-1">
        <Label :for="`filter-${filter.key}`">{{ filter.label }}</Label>
        <select :id="`filter-${filter.key}`" v-model="filters.extra[filter.key]" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
          <option value="">All</option>
          <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <Button type="submit" variant="outline">Apply</Button>
        <Button type="button" @click="startCreate">New</Button>
      </div>
    </form>

    <form v-if="showForm" class="grid max-w-3xl gap-4 rounded-xl border bg-card p-4" @submit.prevent="save">
      <h3 class="font-medium">{{ editingId ? 'Edit' : 'New' }}</h3>
      <Alert v-if="formError" variant="destructive">
        <AlertTitle>{{ formError }}</AlertTitle>
      </Alert>
      <div class="grid gap-3 md:grid-cols-2">
        <div v-for="field in fields" :key="field.key" class="grid gap-1">
          <template v-if="field.type === 'checkbox'">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form[field.key]" type="checkbox">
              {{ field.label }}
            </label>
          </template>
          <template v-else>
            <Label :for="`field-${field.key}`">{{ field.label }}</Label>
            <select
              v-if="field.type === 'select'"
              :id="`field-${field.key}`"
              v-model="form[field.key]"
              class="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
              :required="field.required"
            >
              <option value="">Select</option>
              <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <Input
              v-else
              :id="`field-${field.key}`"
              v-model="form[field.key]"
              :required="field.required"
            />
          </template>
        </div>
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
            <th v-for="column in columns" :key="column.key" class="px-3 py-2 font-medium">{{ column.label }}</th>
            <th class="px-3 py-2 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-b last:border-0">
            <td v-for="column in columns" :key="column.key" class="px-3 py-2">{{ cell(row, column) }}</td>
            <td class="px-3 py-2">
              <button type="button" class="underline" @click="startEdit(row)">Edit</button>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="columns.length + 1" class="px-3 py-6 text-muted-foreground">No rows match these filters.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <Button type="button" variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Previous</Button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <Button type="button" variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next</Button>
    </div>
  </div>
</template>
