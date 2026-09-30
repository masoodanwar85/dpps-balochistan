<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { api, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const data = ref(null)
const loadError = ref('')

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function openAction(kind) {
  const name = {
    deficiency: 'portal-applications',
    incomplete: 'portal-applications',
    document: 'portal-documents',
    staff: 'portal-staff',
    waiting: 'portal-staff',
  }[kind] || 'portal'

  router.push({ name })
}

onMounted(async () => {
  const { response, payload } = await api('/api/v1/portal/dashboard')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The portal could not be loaded.'
    return
  }

  data.value = payload.data
})
</script>

<template>
  <div class="grid gap-4">
    <h2 class="text-lg font-semibold">{{ data?.company_name || 'Company portal' }}</h2>
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <section v-if="data" class="grid gap-3 rounded-md border p-4">
      <p v-if="data.license">
        License: {{ data.license.license_no }}
        · Valid until {{ displayDate(data.license.valid_to) }}
        ({{ data.license.days_remaining }} days)
      </p>
      <p v-else>No current license.</p>
      <p v-if="data.renewal.opens_on">Renewal opens on {{ displayDate(data.renewal.opens_on) }}</p>
      <p v-if="data.renewal.blocked_reason" class="text-sm text-muted-foreground">{{ data.renewal.blocked_reason }}</p>
      <div>
        <Button
          type="button"
          :disabled="!data.renewal.can_start || !auth.can('portal.renewal.submit')"
          @click="router.push({ name: 'portal-applications', query: { renew: '1' } })"
        >
          {{ data.renewal.has_draft ? 'Continue renewal' : 'Start renewal' }}
        </Button>
      </div>
    </section>
    <section v-if="data?.actions?.length" class="grid gap-2">
      <h3 class="font-medium">Action required</h3>
      <div v-for="(action, index) in data.actions" :key="index" class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm">
        <span>{{ action.text }}</span>
        <Button type="button" variant="outline" @click="openAction(action.kind)">Open</Button>
      </div>
    </section>
  </div>
</template>
