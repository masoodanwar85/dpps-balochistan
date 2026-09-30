<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError, upload } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const rows = ref([])
const renewal = ref(null)
const step = ref(0)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const declaration = ref({
  declarant_name: '',
  declarant_cnic: '',
  total_pages: '',
  certified: false,
})

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

async function loadList() {
  const { response, payload } = await api('/api/v1/portal/applications')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Applications could not be loaded.'
    return
  }

  rows.value = payload.data || []
}

async function loadRenewal() {
  const { response, payload } = await api('/api/v1/portal/renewal')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The renewal could not be loaded.'
    return
  }

  renewal.value = payload.data

  if (payload.data.draft) {
    step.value = 1
  }
}

async function start() {
  formError.value = ''
  const { response, payload } = await api('/api/v1/portal/renewal', { method: 'POST', body: {} })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The renewal could not be started.'
    return
  }

  await loadRenewal()
  step.value = 1
}

async function uploadItem(item, event) {
  formError.value = ''
  const form = event.currentTarget
  const file = form.elements.file?.files?.[0]
  const pages = form.elements.page_count?.value
  const typeId = form.elements.document_type_id?.value
  const body = new FormData()
  body.append('file', file)
  body.append('document_type_id', typeId)
  if (pages) {
    body.append('page_count', pages)
  }
  const issue = form.elements.issue_date?.value
  const expiry = form.elements.expiry_date?.value
  if (issue) {
    body.append('issue_date', issue)
  }
  if (expiry) {
    body.append('expiry_date', expiry)
  }
  const { response, payload } = await upload(`/api/v1/portal/renewal/items/${item.id}/upload`, body)

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || (payload?.warnings || []).join(' ') || 'The file could not be uploaded.'
    return
  }

  renewal.value = payload.data
  notice.value = `${item.annex} uploaded.`
}

async function submitRenewal() {
  formError.value = ''
  const { response, payload } = await api('/api/v1/portal/renewal/submit', {
    method: 'POST',
    body: {
      declarant_name: declaration.value.declarant_name,
      declarant_cnic: declaration.value.declarant_cnic,
      total_pages: Number(declaration.value.total_pages),
      certified: declaration.value.certified,
    },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The renewal could not be submitted.'
    return
  }

  step.value = 0
  notice.value = `Application ${payload.data.application_no} submitted. Current stage: ${payload.data.current_stage || 'submitted'}.`
  await Promise.all([loadList(), loadRenewal()])
  step.value = 0
}

onMounted(async () => {
  await Promise.all([loadList(), loadRenewal()])

  if (route.query.renew === '1' && renewal.value?.renewal?.can_start && auth.can('portal.renewal.submit')) {
    if (renewal.value.draft) {
      step.value = 1
    }
  }
})
</script>

<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Applications</h2>
      <Button
        v-if="auth.can('portal.renewal.submit') && renewal?.renewal?.can_start && step === 0"
        type="button"
        @click="start"
      >
        {{ renewal.renewal.has_draft ? 'Continue renewal' : 'Start renewal' }}
      </Button>
    </div>
    <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
    <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
    <p v-if="notice" class="text-sm">{{ notice }}</p>
    <p v-if="renewal && !renewal.renewal.can_start" class="text-sm text-muted-foreground">{{ renewal.renewal.blocked_reason }}</p>

    <section v-if="step > 0 && renewal?.draft" class="grid gap-4 rounded-md border p-4">
      <p class="text-sm">Step {{ step }} of 4 · {{ renewal.draft.application_no }}</p>
      <div v-if="step === 1" class="grid gap-2 text-sm">
        <p>Verified technical staff: {{ renewal.verified_technical_staff }} (minimum {{ renewal.minimum_technical_staff }})</p>
        <p v-if="renewal.license">License {{ renewal.license.license_no }}, valid until {{ displayDate(renewal.license.valid_to) }}</p>
        <Button type="button" @click="step = 2">Continue</Button>
      </div>
      <div v-else-if="step === 2" class="grid gap-4">
        <form
          v-for="item in renewal.draft.items.filter((row) => row.portal_uploadable && row.requires_upload)"
          :key="item.id"
          class="grid gap-2 rounded-md border p-3 text-sm"
          @submit.prevent="uploadItem(item, $event)"
        >
          <p class="font-medium">{{ item.annex }} · {{ item.title }}</p>
          <p>Status: {{ item.status }}<span v-if="item.page_count"> · {{ item.page_count }} pages</span></p>
          <select name="document_type_id" class="border-input h-9 rounded-md border px-3" required>
            <option value="">Document type</option>
            <option v-for="type in renewal.document_types" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
          <input name="file" type="file" required>
          <Input name="page_count" type="number" min="1" placeholder="Pages" />
          <Input v-if="item.requires_validity_dates" name="issue_date" type="date" required />
          <Input v-if="item.requires_validity_dates" name="expiry_date" type="date" required />
          <Button type="submit" variant="outline">Upload</Button>
        </form>
        <div class="flex gap-2">
          <Button type="button" variant="outline" @click="step = 1">Back</Button>
          <Button type="button" @click="step = 3">Continue</Button>
        </div>
      </div>
      <div v-else-if="step === 3" class="grid gap-2 text-sm">
        <p v-if="renewal.draft.fee">{{ renewal.draft.fee.label }}: Rs {{ renewal.draft.fee.amount }}</p>
        <p v-else>Fee is not configured for this date.</p>
        <p>{{ renewal.draft.fee_note }}</p>
        <div class="flex gap-2">
          <Button type="button" variant="outline" @click="step = 2">Back</Button>
          <Button type="button" @click="step = 4">Continue</Button>
        </div>
      </div>
      <form v-else class="grid max-w-lg gap-3" @submit.prevent="submitRenewal">
        <p class="text-sm">{{ renewal.declaration }}</p>
        <div class="grid gap-1"><Label for="declarant">Name</Label><Input id="declarant" v-model="declaration.declarant_name" required /></div>
        <div class="grid gap-1"><Label for="declarant-cnic">CNIC</Label><Input id="declarant-cnic" v-model="declaration.declarant_cnic" required /></div>
        <div class="grid gap-1"><Label for="pages">Total pages</Label><Input id="pages" v-model="declaration.total_pages" type="number" min="1" required /></div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="declaration.certified" type="checkbox" required>
          I certify the statement above.
        </label>
        <div class="flex gap-2">
          <Button type="button" variant="outline" @click="step = 3">Back</Button>
          <Button type="submit">Submit</Button>
        </div>
      </form>
    </section>

    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Number</th>
            <th class="px-3 py-2">Type</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Stage</th>
            <th class="px-3 py-2">Payable</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.application_no }}</td>
            <td class="px-3 py-2">{{ row.application_type }}</td>
            <td class="px-3 py-2">{{ row.status }}</td>
            <td class="px-3 py-2">{{ row.current_stage || '—' }}</td>
            <td class="px-3 py-2">{{ row.total_payable }}</td>
          </tr>
          <tr v-if="rows.length === 0">
            <td class="text-muted-foreground px-3 py-4" colspan="5">No applications.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <section v-for="row in rows.filter((item) => item.letters?.some((letter) => letter.status === 'open'))" :key="`letter-${row.id}`" class="text-sm">
      <p v-for="letter in row.letters" :key="letter.id">
        {{ letter.letter_no }} · {{ letter.item_count === 1 ? '1 item' : `${letter.item_count} items` }} · due {{ displayDate(letter.reply_due_date) }}
      </p>
    </section>
  </div>
</template>
