<script setup>
import { onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
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
  <div class="grid gap-6">
    <PageSection accent="sky" eyebrow="Company portal" title="Staff">
      <template #actions>
        <Button type="button" size="sm" @click="open = !open">Add staff</Button>
      </template>
      <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
      <p v-if="notice" class="text-sm">{{ notice }}</p>
    </PageSection>
    <PageSection v-if="open" accent="violet" eyebrow="Staff" title="Add staff">
      <form class="grid max-w-lg gap-3" @submit.prevent="addStaff">
        <div class="dpps-field">
          <Label for="cnic">CNIC</Label>
          <Input id="cnic" v-model="form.cnic" required />
        </div>
        <Button type="button" variant="outline" @click="checkPerson">Check CNIC</Button>
        <p v-if="preview?.found">{{ preview.person.full_name }}</p>
        <ul v-if="preview?.blocks?.length" class="text-sm">
          <li v-for="block in preview.blocks" :key="block.message">{{ block.message }}</li>
        </ul>
        <div class="dpps-field">
          <Label for="full-name">Name</Label>
          <Input id="full-name" v-model="form.full_name" />
        </div>
        <div class="dpps-field">
          <Label for="mobile">Mobile</Label>
          <Input id="mobile" v-model="form.mobile" />
        </div>
        <div class="dpps-field">
          <Label for="start-date">Since</Label>
          <Input id="start-date" v-model="form.start_date" type="date" required />
        </div>
        <div class="dpps-field">
          <Label for="qualification">Qualification</Label>
          <select id="qualification" v-model="form.qualification_id" class="dpps-select">
            <option value="">None</option>
            <option v-for="row in qualifications" :key="row.id" :value="row.id">{{ row.name }}</option>
          </select>
        </div>
        <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
        <Button type="submit">Submit</Button>
      </form>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>CNIC</th>
              <th>Qualification</th>
              <th>Since</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.full_name }}</td>
              <td>{{ row.cnic_display || row.cnic }}</td>
              <td>{{ row.qualification || '—' }}</td>
              <td>{{ displayDate(row.start_date) }}</td>
              <td>{{ row.end_date ? 'Ended' : statusLabel(row) }}</td>
              <td>
                <Button v-if="!row.end_date" type="button" variant="outline" size="sm" @click="endId = row.id">End employment</Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>

    <PageSection v-if="endId" accent="rose" eyebrow="Staff" title="End employment">
      <form class="grid max-w-lg gap-3" @submit.prevent="endStaff">
        <div class="dpps-field">
          <Label for="end-date">End date</Label>
          <Input id="end-date" v-model="form.end_date" type="date" required />
        </div>
        <div class="dpps-field">
          <Label for="end-reason">Reason</Label>
          <Input id="end-reason" v-model="form.end_reason" required />
        </div>
        <Button type="submit">Save</Button>
      </form>
    </PageSection>
  </div>
</template>
