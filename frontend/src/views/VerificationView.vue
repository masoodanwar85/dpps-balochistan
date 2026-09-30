<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { api, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const rows = ref([])
const counts = ref({ staff: 0, documents: 0, products: 0 })
const review = ref(null)
const loadError = ref('')
const actionError = ref('')
const reason = ref('')
const busy = ref(false)

const tabs = computed(() => [
  { type: 'staff', label: 'Staff', count: counts.value.staff, permission: 'staff.verify' },
  { type: 'document', label: 'Documents', count: counts.value.documents, permission: 'documents.verify' },
  { type: 'product', label: 'Products', count: counts.value.products, permission: 'products.verify' },
].filter((tab) => auth.can(tab.permission)))

const activeType = computed(() => {
  const requested = String(route.query.type || '')
  if (tabs.value.some((tab) => tab.type === requested)) {
    return requested
  }

  return tabs.value[0]?.type || 'staff'
})

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

async function load() {
  loadError.value = ''
  review.value = null
  reason.value = ''
  const { response, payload } = await api(`/api/v1/verifications?type=${activeType.value}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The queue could not be loaded.'
    rows.value = []
    return
  }

  rows.value = payload.data || []
  counts.value = payload.meta?.counts || counts.value
}

async function openReview(row) {
  actionError.value = ''
  reason.value = ''
  const { response, payload } = await api(`/api/v1/verifications/${row.type}/${row.id}`)

  if (!response.ok) {
    actionError.value = firstError(payload?.errors) || 'This item could not be opened.'
    review.value = null
    return
  }

  review.value = payload.data
}

async function decide(action) {
  if (!review.value) {
    return
  }

  busy.value = true
  actionError.value = ''
  const body = action === 'reject' ? { reason: reason.value } : undefined
  const { response, payload } = await api(
    `/api/v1/verifications/${review.value.type}/${review.value.id}/${action}`,
    { method: 'POST', body: body ?? {} },
  )
  busy.value = false

  if (!response.ok) {
    actionError.value = firstError(payload?.errors) || 'The decision could not be saved.'
    return
  }

  await load()
}

async function openFile(id) {
  actionError.value = ''
  const { response, payload } = await api(`/api/v1/documents/${id}/download-url`)

  if (!response.ok || !payload?.data?.url) {
    actionError.value = firstError(payload?.errors) || 'The file could not be opened.'
    return
  }

  window.open(payload.data.url, '_blank', 'noopener')
}

function selectTab(type) {
  router.replace({ name: 'verification', query: { type } })
}

watch(activeType, () => {
  load()
})

onMounted(load)
</script>

<template>
  <div class="grid gap-4">
    <h2 class="text-lg font-semibold">Verification queue</h2>
    <div class="flex flex-wrap gap-2">
      <Button
        v-for="tab in tabs"
        :key="tab.type"
        type="button"
        :variant="tab.type === activeType ? 'default' : 'outline'"
        @click="selectTab(tab.type)"
      >
        {{ tab.label }} ({{ tab.count }})
      </Button>
    </div>
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-sm">
          <thead class="bg-muted/50 text-left">
            <tr>
              <th class="px-3 py-2">Applicant</th>
              <th class="px-3 py-2">Item</th>
              <th class="px-3 py-2">Submitted</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.type}-${row.id}`" class="border-t">
              <td class="px-3 py-2">{{ row.applicant }}</td>
              <td class="px-3 py-2">{{ row.item }}</td>
              <td class="px-3 py-2">{{ displayDate(row.submitted_at) }}</td>
              <td class="px-3 py-2">
                <Button type="button" variant="outline" @click="openReview(row)">Review</Button>
              </td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground px-3 py-4" colspan="4">Nothing is waiting in this tab.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <aside v-if="review" class="grid gap-3 rounded-md border p-4">
        <div>
          <p class="text-sm font-medium">{{ review.applicant }}</p>
          <p class="text-muted-foreground text-sm">{{ review.item }}</p>
        </div>
        <dl class="grid gap-2 text-sm">
          <div v-for="field in review.fields" :key="field.label" class="grid grid-cols-[7rem_1fr] gap-2">
            <dt class="text-muted-foreground">{{ field.label }}</dt>
            <dd>{{ field.value || '—' }}</dd>
          </div>
        </dl>
        <div v-if="review.files?.length" class="grid gap-2">
          <Button
            v-for="file in review.files"
            :key="file.id"
            type="button"
            variant="outline"
            @click="openFile(file.id)"
          >
            Open {{ file.title }}
          </Button>
        </div>
        <ul v-if="review.conflicts?.length" class="text-sm">
          <li v-for="message in review.conflicts" :key="message">{{ message }}</li>
        </ul>
        <Alert v-if="actionError" variant="destructive">
          <AlertTitle>{{ actionError }}</AlertTitle>
        </Alert>
        <label class="grid gap-1 text-sm">
          Rejection reason
          <textarea v-model="reason" rows="3" class="border-input rounded-md border px-3 py-2" />
        </label>
        <div class="flex gap-2">
          <Button type="button" :disabled="busy" @click="decide('approve')">Approve</Button>
          <Button type="button" variant="outline" :disabled="busy" @click="decide('reject')">Reject</Button>
        </div>
      </aside>
    </div>
  </div>
</template>
