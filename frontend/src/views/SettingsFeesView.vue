<script setup>
import { computed, onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const rows = ref([])
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const saving = ref(false)

const today = () => new Date().toISOString().slice(0, 10)

const companyRows = computed(() => rows.value.filter((row) => row.entity_type === 'company'))
const dealerRows = computed(() => rows.value.filter((row) => row.entity_type === 'dealer'))

function displayAmount(value) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return Number(value).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).split('-')

  return `${day}-${month}-${year}`
}

async function load() {
  const { response, payload } = await api('/api/v1/fees')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load fees.'

    return
  }

  rows.value = (payload.data || []).map((row) => ({
    ...row,
    amount: row.current?.amount ?? '',
    effective_from: today(),
    notes: '',
  }))
}

async function save() {
  saving.value = true
  formError.value = ''
  notice.value = ''

  const { response, payload } = await api('/api/v1/fees', {
    method: 'PUT',
    body: {
      fees: rows.value.map((row) => ({
        entity_type: row.entity_type,
        fee_type: row.fee_type,
        amount: Number(row.amount),
        effective_from: row.effective_from,
        notes: row.notes || null,
      })),
    },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save fees.'

    return
  }

  rows.value = (payload.data || []).map((row) => ({
    ...row,
    amount: row.current?.amount ?? '',
    effective_from: today(),
    notes: '',
  }))
  notice.value = 'Fees saved. Changed rates closed the previous period and started a new one.'
}

onMounted(load)
</script>

<template>
  <form class="grid gap-6" @submit.prevent="save">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="formError" variant="destructive">
      <AlertTitle>{{ formError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <PageSection accent="slate" eyebrow="Settings" title="Fees">
      <p class="text-sm text-muted-foreground">
        Registration and renewal only. Changing an amount closes the previous period the day before the new effective date.
      </p>
    </PageSection>

    <PageSection accent="emerald" eyebrow="Company" title="Company fees">
      <div class="grid gap-6">
        <div v-for="row in companyRows" :key="`${row.entity_type}-${row.fee_type}`" class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <p class="font-medium">{{ row.label }}</p>
            <p class="text-sm text-muted-foreground">
              Current: Rs {{ displayAmount(row.current?.amount) }}
              <template v-if="row.current"> from {{ displayDate(row.current.effective_from) }}</template>
            </p>
          </div>
          <div class="dpps-field">
            <Label :for="`${row.entity_type}-${row.fee_type}-amount`">Amount (Rs)</Label>
            <Input :id="`${row.entity_type}-${row.fee_type}-amount`" v-model="row.amount" type="number" min="0.01" step="0.01" required />
          </div>
          <div class="dpps-field">
            <Label :for="`${row.entity_type}-${row.fee_type}-from`">New effective from</Label>
            <Input :id="`${row.entity_type}-${row.fee_type}-from`" v-model="row.effective_from" type="date" required />
          </div>
        </div>
      </div>
    </PageSection>

    <PageSection accent="sky" eyebrow="Dealer" title="Dealer fees">
      <div class="grid gap-6">
        <div v-for="row in dealerRows" :key="`${row.entity_type}-${row.fee_type}`" class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <p class="font-medium">{{ row.label }}</p>
            <p class="text-sm text-muted-foreground">
              Current: Rs {{ displayAmount(row.current?.amount) }}
              <template v-if="row.current"> from {{ displayDate(row.current.effective_from) }}</template>
            </p>
          </div>
          <div class="dpps-field">
            <Label :for="`${row.entity_type}-${row.fee_type}-amount`">Amount (Rs)</Label>
            <Input :id="`${row.entity_type}-${row.fee_type}-amount`" v-model="row.amount" type="number" min="0.01" step="0.01" required />
          </div>
          <div class="dpps-field">
            <Label :for="`${row.entity_type}-${row.fee_type}-from`">New effective from</Label>
            <Input :id="`${row.entity_type}-${row.fee_type}-from`" v-model="row.effective_from" type="date" required />
          </div>
        </div>
      </div>
    </PageSection>

    <PageSection accent="slate" eyebrow="Settings" title="Save">
      <Button type="submit" :disabled="saving">Save</Button>
    </PageSection>
  </form>
</template>
