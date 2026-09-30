<script setup>
import { onMounted, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const rows = ref([])
const qualifications = ref([])
const open = ref(false)
const formError = ref('')
const loadError = ref('')
const notice = ref('')
const preview = ref(null)
const endId = ref(null)
const form = ref({
  cnic: '',
  full_name: '',
  mobile: '',
  start_date: '',
  qualification_id: '',
  end_date: '',
  end_reason: '',
})

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

  if (row.verification_status === 'verified') {
    return 'Verified'
  }

  return 'Pending'
}

async function load() {
  const { response, payload } = await api('/api/v1/portal/staff')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Staff could not be loaded.'
    return
  }

  rows.value = payload.data || []
  qualifications.value = payload.meta?.qualifications || []
}

async function checkPerson() {
  formError.value = ''
  preview.value = null
  const { response, payload } = await api('/api/v1/portal/staff/check', {
    method: 'POST',
    body: { cnic: form.value.cnic, role: 'technical_staff' },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'This CNIC could not be checked.'
    return
  }

  preview.value = payload.data
}

async function addStaff() {
  formError.value = ''
  const { response, payload } = await api('/api/v1/portal/staff', {
    method: 'POST',
    body: {
      role: 'technical_staff',
      cnic: form.value.cnic,
      full_name: form.value.full_name || null,
      mobile: form.value.mobile || null,
      start_date: form.value.start_date,
      qualification_id: form.value.qualification_id || null,
    },
  })

  if (response.status === 409) {
    formError.value = (payload?.warnings || []).join(' ')
    return
  }

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Staff could not be added.'
    return
  }

  open.value = false
  notice.value = 'Staff submitted. The Directorate will verify this record.'
  await load()
}

async function endStaff() {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/portal/staff/${endId.value}/end`, {
    method: 'POST',
    body: { end_date: form.value.end_date, end_reason: form.value.end_reason },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Employment could not be ended.'
    return
  }

  endId.value = null
  notice.value = 'Employment ended.'
  await load()
}

onMounted(load)
</script>

<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Staff</h2>
      <Button type="button" @click="open = !open">Add staff</Button>
    </div>
    <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
    <p v-if="notice" class="text-sm">{{ notice }}</p>
    <form v-if="open" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="addStaff">
      <div class="grid gap-1">
        <Label for="cnic">CNIC</Label>
        <Input id="cnic" v-model="form.cnic" required />
      </div>
      <Button type="button" variant="outline" @click="checkPerson">Check CNIC</Button>
      <p v-if="preview?.found">{{ preview.person.full_name }}</p>
      <ul v-if="preview?.blocks?.length" class="text-sm">
        <li v-for="block in preview.blocks" :key="block.message">{{ block.message }}</li>
      </ul>
      <div class="grid gap-1">
        <Label for="full-name">Name</Label>
        <Input id="full-name" v-model="form.full_name" />
      </div>
      <div class="grid gap-1">
        <Label for="mobile">Mobile</Label>
        <Input id="mobile" v-model="form.mobile" />
      </div>
      <div class="grid gap-1">
        <Label for="start-date">Since</Label>
        <Input id="start-date" v-model="form.start_date" type="date" required />
      </div>
      <div class="grid gap-1">
        <Label for="qualification">Qualification</Label>
        <select id="qualification" v-model="form.qualification_id" class="border-input h-9 rounded-md border px-3 text-sm">
          <option value="">None</option>
          <option v-for="row in qualifications" :key="row.id" :value="row.id">{{ row.name }}</option>
        </select>
      </div>
      <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
      <Button type="submit">Submit</Button>
    </form>
    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Name</th>
            <th class="px-3 py-2">CNIC</th>
            <th class="px-3 py-2">Qualification</th>
            <th class="px-3 py-2">Since</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.full_name }}</td>
            <td class="px-3 py-2">{{ row.cnic_display || row.cnic }}</td>
            <td class="px-3 py-2">{{ row.qualification || '—' }}</td>
            <td class="px-3 py-2">{{ displayDate(row.start_date) }}</td>
            <td class="px-3 py-2">{{ row.end_date ? 'Ended' : statusLabel(row) }}</td>
            <td class="px-3 py-2">
              <Button v-if="!row.end_date" type="button" variant="outline" @click="endId = row.id">End employment</Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <form v-if="endId" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="endStaff">
      <div class="grid gap-1">
        <Label for="end-date">End date</Label>
        <Input id="end-date" v-model="form.end_date" type="date" required />
      </div>
      <div class="grid gap-1">
        <Label for="end-reason">Reason</Label>
        <Input id="end-reason" v-model="form.end_reason" required />
      </div>
      <Button type="submit">Save</Button>
    </form>
  </div>
</template>
