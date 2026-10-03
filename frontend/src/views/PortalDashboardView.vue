<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
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
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>

    <PageSection accent="emerald" eyebrow="Company portal" :title="data?.company_name || 'Company portal'">
      <template v-if="data">
        <div v-if="data.license" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
          <p class="text-xs font-medium text-emerald-800">Current license</p>
          <p class="mt-1 text-lg font-semibold text-emerald-950">{{ data.license.license_no }}</p>
          <p class="mt-1 text-sm text-emerald-900">
            Valid until {{ displayDate(data.license.valid_to) }}
            ({{ data.license.days_remaining }} days)
          </p>
        </div>
        <p v-else class="text-sm text-muted-foreground">No current license.</p>
        <p v-if="data.renewal.opens_on" class="mt-3 text-sm">Renewal opens on {{ displayDate(data.renewal.opens_on) }}</p>
        <p v-if="data.renewal.blocked_reason" class="mt-2 text-sm text-muted-foreground">{{ data.renewal.blocked_reason }}</p>
        <div class="mt-4">
          <Button
            type="button"
            :disabled="!data.renewal.can_start || !auth.can('portal.renewal.submit')"
            @click="router.push({ name: 'portal-applications', query: { renew: '1' } })"
          >
            {{ data.renewal.has_draft ? 'Continue renewal' : 'Start renewal' }}
          </Button>
        </div>
      </template>
    </PageSection>

    <PageSection v-if="data?.actions?.length" accent="amber" eyebrow="Tasks" title="Action required">
      <div class="grid gap-2">
        <div
          v-for="(action, index) in data.actions"
          :key="index"
          class="flex items-center justify-between gap-3 rounded-lg border bg-amber-50/60 px-3 py-2.5 text-sm"
        >
          <span>{{ action.text }}</span>
          <Button type="button" variant="outline" size="sm" @click="openAction(action.kind)">Open</Button>
        </div>
      </div>
    </PageSection>
  </div>
</template>
