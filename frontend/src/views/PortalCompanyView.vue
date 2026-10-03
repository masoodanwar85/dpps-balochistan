<script setup>
import { onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
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
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <PageSection accent="emerald" eyebrow="Company portal" title="Company information">
      <dl v-if="data" class="grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Name</dt><dd class="font-medium">{{ data.company.name }}</dd></div>
        <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Legal type</dt><dd class="font-medium">{{ labels[data.company.legal_type] || data.company.legal_type }}</dd></div>
        <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">NTN</dt><dd class="font-medium">{{ data.company.ntn || '—' }}</dd></div>
        <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Incorporation</dt><dd class="font-medium">{{ data.company.incorporation_no || '—' }} · {{ displayDate(data.company.incorporation_date) }}</dd></div>
        <div class="rounded-lg border bg-muted/30 px-3 py-2 sm:col-span-2">
          <dt class="text-xs text-muted-foreground">Head office</dt>
          <dd class="font-medium">
            {{ data.company.head_office_address }}
            <template v-if="data.company.city && !String(data.company.head_office_address).includes(data.company.city)">, {{ data.company.city }}</template>
          </dd>
        </div>
      </dl>
    </PageSection>

    <PageSection v-if="data" accent="sky" eyebrow="People" title="CEO and directors">
      <p v-if="data.leadership.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul v-else class="grid gap-2 text-sm">
        <li v-for="row in data.leadership" :key="row.id" class="rounded-lg border px-3 py-2">{{ row.role_label }}: {{ row.full_name }}</li>
      </ul>
    </PageSection>

    <PageSection v-if="data" accent="violet" eyebrow="Sites" title="Premises">
      <p v-if="data.premises.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul v-else class="grid gap-2 text-sm">
        <li v-for="row in data.premises" :key="row.id" class="rounded-lg border px-3 py-2">{{ row.type }} · {{ row.address }} · {{ row.district_name }}</li>
      </ul>
    </PageSection>

    <PageSection v-if="data" accent="amber" eyebrow="Assets" title="Assets">
      <p v-if="data.assets.length === 0" class="text-sm text-muted-foreground">None on record.</p>
      <ul v-else class="grid gap-2 text-sm">
        <li v-for="row in data.assets" :key="row.id" class="rounded-lg border px-3 py-2">{{ row.asset_type }} · {{ row.description }}</li>
      </ul>
    </PageSection>

    <p v-if="data" class="rounded-xl border bg-card px-4 py-3 text-sm shadow-sm">{{ data.contact_message }}</p>
  </div>
</template>
