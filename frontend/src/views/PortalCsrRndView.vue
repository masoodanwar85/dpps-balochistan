<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError, upload } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const rows = ref([])
const kinds = ref([])
const open = ref(false)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const form = ref({ kind: '', title: '', file: null })
const enabled = computed(() => Boolean(auth.user?.company_csr || auth.user?.company_rnd))

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

async function load() {
  if (!enabled.value) {
    loadError.value = 'CSR/R&D is not enabled for this company.'

    return
  }

  const { response, payload } = await api('/api/v1/portal/csr-rnd')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'CSR/R&D files could not be loaded.'

    return
  }

  rows.value = payload.data || []
  kinds.value = payload.meta?.kinds || []
}

async function save() {
  formError.value = ''
  const body = new FormData()
  body.append('kind', form.value.kind)
  body.append('title', form.value.title)
  body.append('file', form.value.file)
  const { response, payload } = await upload('/api/v1/portal/csr-rnd', body)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The file could not be saved.'

    return
  }

  open.value = false
  form.value = { kind: kinds.value[0]?.value || '', title: '', file: null }
  notice.value = 'File uploaded.'
  await load()
}

async function openFile(row) {
  const { response, payload } = await api(`/api/v1/csr-rnd-files/${row.id}/download-url`)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The file could not be opened.'

    return
  }

  window.open(payload.data.url, '_blank', 'noopener')
}

function onFile(event) {
  form.value.file = event.target.files?.[0] || null
}

onMounted(async () => {
  await auth.fetchUser(true)

  if (!enabled.value) {
    await router.replace({ name: 'portal' })

    return
  }

  await load()
})
</script>

<template>
  <div class="grid gap-6">
    <PageSection accent="amber" eyebrow="Company portal" title="CSR/R&D">
      <template #actions>
        <Button type="button" size="sm" @click="open = true; form.kind = kinds[0]?.value || ''">Upload</Button>
      </template>
      <p class="text-sm text-muted-foreground">Upload photos or documents for CSR and R&amp;D.</p>
      <Alert v-if="loadError" variant="destructive" class="mt-4"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
      <p v-if="notice" class="mt-2 text-sm">{{ notice }}</p>
    </PageSection>

    <PageSection v-if="open" accent="violet" eyebrow="CSR / R&D" title="Upload file">
      <form class="grid max-w-lg gap-3" @submit.prevent="save">
        <div class="dpps-field">
          <Label for="kind">Type</Label>
          <select id="kind" v-model="form.kind" class="dpps-select" required>
            <option value="">Choose</option>
            <option v-for="item in kinds" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="title">Title</Label>
          <Input id="title" v-model="form.title" required />
        </div>
        <div class="dpps-field">
          <Label for="file">File</Label>
          <input id="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="text-sm" required @change="onFile">
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
              <th>File</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.title }}</td>
              <td>{{ row.kind_label }}</td>
              <td>{{ row.original_name }}</td>
              <td class="tabular-nums">{{ displayDate(row.created_at) }}</td>
              <td><Button type="button" variant="outline" size="sm" @click="openFile(row)">View</Button></td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground" colspan="5">No CSR/R&amp;D files.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>
  </div>
</template>
