<script setup>
import { onMounted, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { api, firstError } from '@/lib/api'

const data = ref(null)
const loadError = ref('')

const labels = {
  private_ltd: 'Private limited',
  public_ltd: 'Public limited',
  partnership: 'Partnership',
  sole_proprietor: 'Sole proprietor',
  other: 'Other',
}

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

onMounted(async () => {
  const { response, payload } = await api('/api/v1/portal/company')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Company information could not be loaded.'
    return
  }

  data.value = payload.data
})
</script>

<template>
  <div class="grid gap-4">
    <h2 class="text-lg font-semibold">Company information</h2>
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <dl v-if="data" class="grid gap-2 text-sm">
      <div class="grid grid-cols-[12rem_1fr] gap-2"><dt class="text-muted-foreground">Name</dt><dd>{{ data.company.name }}</dd></div>
      <div class="grid grid-cols-[12rem_1fr] gap-2"><dt class="text-muted-foreground">Legal type</dt><dd>{{ labels[data.company.legal_type] || data.company.legal_type }}</dd></div>
      <div class="grid grid-cols-[12rem_1fr] gap-2"><dt class="text-muted-foreground">NTN</dt><dd>{{ data.company.ntn || '—' }}</dd></div>
      <div class="grid grid-cols-[12rem_1fr] gap-2"><dt class="text-muted-foreground">Incorporation</dt><dd>{{ data.company.incorporation_no || '—' }} · {{ displayDate(data.company.incorporation_date) }}</dd></div>
      <div class="grid grid-cols-[12rem_1fr] gap-2"><dt class="text-muted-foreground">Head office</dt><dd>{{ data.company.head_office_address }}<template v-if="data.company.city && !String(data.company.head_office_address).includes(data.company.city)">, {{ data.company.city }}</template></dd></div>
    </dl>
    <section v-if="data">
      <h3 class="mb-2 font-medium">CEO and directors</h3>
      <p v-if="data.leadership.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul class="text-sm">
        <li v-for="row in data.leadership" :key="row.id">{{ row.role_label }}: {{ row.full_name }}</li>
      </ul>
    </section>
    <section v-if="data">
      <h3 class="mb-2 font-medium">Premises</h3>
      <p v-if="data.premises.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul class="text-sm">
        <li v-for="row in data.premises" :key="row.id">{{ row.type }} · {{ row.address }} · {{ row.district_name }}</li>
      </ul>
    </section>
    <section v-if="data">
      <h3 class="mb-2 font-medium">Assets</h3>
      <p v-if="data.assets.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul class="text-sm">
        <li v-for="row in data.assets" :key="row.id">{{ row.asset_type }} · {{ row.description }}</li>
      </ul>
    </section>
    <p v-if="data" class="rounded-md border px-3 py-2 text-sm">{{ data.contact_message }}</p>
  </div>
</template>
