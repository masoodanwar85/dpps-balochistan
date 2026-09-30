<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const route = useRoute()
const router = useRouter()

const partyType = ref('company')
const search = ref('')
const matches = ref([])
const selected = ref(null)
const licenseNo = ref('')
const licenseKind = ref('registration')
const validTo = ref('')
const validFrom = ref('')
const latestEnd = ref('')
const saving = ref(false)
const searching = ref(false)
const loadError = ref('')
const notice = ref('')

const partyLabel = computed(() => (partyType.value === 'dealer' ? 'Dealer' : 'Company'))
const latestEndLabel = computed(() => displayDate(latestEnd.value))

function displayDate(value) {
  if (!value) {
    return ''
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function partyName(row) {
  if (!row) {
    return ''
  }

  if (partyType.value === 'dealer' || row.dealer_code) {
    return `${row.shop_name} (${row.dealer_code})`
  }

  return `${row.name} (${row.company_code})`
}

async function loadPreview() {
  const params = new URLSearchParams({ licensable_type: partyType.value })

  if (validTo.value) {
    params.set('valid_to', validTo.value)
  }

  const { response, payload } = await api(`/api/v1/licenses/previous?${params}`)

  if (!response.ok) {
    return
  }

  latestEnd.value = payload.data?.latest_end_date || ''
  validFrom.value = payload.data?.valid_from || ''
}

async function loadParty(type, id) {
  const path = type === 'dealer' ? `/api/v1/dealers/${id}` : `/api/v1/companies/${id}`
  const { response, payload } = await api(path)

  if (response.ok) {
    selected.value = payload.data
  }
}

async function findParties() {
  notice.value = ''
  loadError.value = ''
  const term = search.value.trim()

  if (term.length < 2) {
    loadError.value = 'Type at least 2 characters to search.'
    matches.value = []
    return
  }

  searching.value = true
  const path = partyType.value === 'dealer' ? '/api/v1/dealers' : '/api/v1/companies'
  const { response, payload } = await api(`${path}?search=${encodeURIComponent(term)}&per_page=8`)
  searching.value = false

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The search could not be loaded.'
    matches.value = []
    return
  }

  matches.value = payload.data || []
}

function choose(row) {
  selected.value = row
  matches.value = []
  search.value = ''
  notice.value = ''
  loadError.value = ''
}

async function save() {
  notice.value = ''
  loadError.value = ''

  if (!selected.value) {
    loadError.value = `Choose a ${partyLabel.value.toLowerCase()}.`
    return
  }

  if (latestEnd.value && validTo.value > latestEnd.value) {
    loadError.value = `The end date must be ${latestEndLabel.value} or earlier.`
    return
  }

  saving.value = true
  const { response, payload } = await api('/api/v1/licenses/previous', {
    method: 'POST',
    body: {
      licensable_type: partyType.value,
      licensable_id: selected.value.id,
      license_no: licenseNo.value.trim(),
      license_kind: licenseKind.value,
      valid_to: validTo.value,
    },
  })
  saving.value = false

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The license could not be saved.'
    return
  }

  const history = payload.data.status === 'superseded' ? ' A later license is already on file, so this one is kept as history.' : ''
  notice.value = `Saved ${payload.data.license_no} for ${partyName(selected.value)}. It runs from ${displayDate(payload.data.valid_from)} to ${displayDate(payload.data.valid_to)}.${history}`
  licenseNo.value = ''
  validTo.value = ''
  validFrom.value = ''
}

watch(partyType, () => {
  selected.value = null
  matches.value = []
  search.value = ''
  validFrom.value = ''
  loadPreview()
})

watch(validTo, () => {
  loadPreview()
})

onMounted(async () => {
  const type = route.query.type === 'dealer' ? 'dealer' : 'company'
  partyType.value = type
  await loadPreview()
  const id = Number(route.query.id)

  if (id) {
    await loadParty(type, id)
  }
})
</script>

<template>
  <div class="grid max-w-xl gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <h1 class="text-xl font-semibold">Record a previous license</h1>
      <Button type="button" variant="outline" @click="router.push({ name: 'licenses' })">Back to licenses</Button>
    </div>

    <p class="text-muted-foreground text-sm">
      Use this for a license that was already granted. The end date can be {{ latestEndLabel || 'the last day of this year' }} or any earlier date.
      The start date is the end date minus the license period, plus one day.
    </p>

    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <form class="grid gap-3" @submit.prevent="save">
      <div class="grid gap-1">
        <Label for="prev-type">Company or dealer</Label>
        <select id="prev-type" v-model="partyType" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Company or dealer">
          <option value="company">Company</option>
          <option value="dealer">Dealer</option>
        </select>
      </div>

      <div v-if="selected" class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm">
        <span>{{ partyLabel }}: {{ partyName(selected) }}</span>
        <Button type="button" variant="outline" @click="selected = null">Change</Button>
      </div>

      <div v-else class="grid gap-2">
        <div class="grid gap-1">
          <Label for="prev-search">Find {{ partyLabel.toLowerCase() }}</Label>
          <div class="flex gap-2">
            <Input id="prev-search" v-model="search" :aria-label="`Find ${partyLabel.toLowerCase()}`" @keyup.enter="findParties" />
            <Button type="button" variant="outline" :disabled="searching" @click="findParties">Search</Button>
          </div>
        </div>
        <div v-if="matches.length" class="grid gap-1">
          <Button v-for="row in matches" :key="row.id" type="button" variant="outline" class="justify-start" @click="choose(row)">
            {{ partyName(row) }}
          </Button>
        </div>
      </div>

      <div class="grid gap-1">
        <Label for="prev-no">License number</Label>
        <Input id="prev-no" v-model="licenseNo" required maxlength="100" aria-label="License number" />
      </div>

      <div class="grid gap-1">
        <Label for="prev-kind">Kind</Label>
        <select id="prev-kind" v-model="licenseKind" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" aria-label="Kind">
          <option value="registration">Registration</option>
          <option value="renewal">Renewal</option>
        </select>
      </div>

      <div class="grid gap-1">
        <Label for="prev-end">End date</Label>
        <Input id="prev-end" v-model="validTo" type="date" :max="latestEnd" required aria-label="End date" />
        <p class="text-muted-foreground text-sm">Latest date: {{ latestEndLabel || '—' }}</p>
      </div>

      <div class="grid gap-1">
        <Label for="prev-start">Start date</Label>
        <Input id="prev-start" :model-value="validFrom" type="date" disabled aria-label="Start date" />
      </div>

      <Button type="submit" :disabled="saving">Save license</Button>
    </form>
  </div>
</template>
