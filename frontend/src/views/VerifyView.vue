<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { api } from '@/lib/api'

const route = useRoute()
const record = ref(null)
const missing = ref(false)
const failed = ref(false)

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function statusLabel(value) {
  return ({
    active: 'Active',
    expired: 'Expired',
    suspended: 'Suspended',
    cancelled: 'Cancelled',
    superseded: 'Superseded',
  })[value] || value
}

onMounted(async () => {
  const { response, payload } = await api(`/api/v1/public/verify/${route.params.token}`)

  if (response.status === 404) {
    missing.value = true
    return
  }

  if (!response.ok) {
    failed.value = true
    return
  }

  record.value = payload.data
})
</script>

<template>
  <main class="min-h-screen bg-muted p-6">
    <div class="mx-auto grid max-w-lg content-center gap-6 py-10">
      <PageSection v-if="missing" accent="rose" eyebrow="Verification" title="Certificate not found">
        <p class="text-sm text-muted-foreground">No license matches this code.</p>
      </PageSection>
      <PageSection v-else-if="failed" accent="rose" eyebrow="Verification" title="Could not be checked">
        <p class="text-sm text-muted-foreground">The certificate could not be checked.</p>
      </PageSection>
      <PageSection v-else-if="record" accent="emerald" eyebrow="Directorate of Plant Protection, Balochistan" title="Verified license">
        <p v-if="record.banner" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200">{{ record.banner }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 sm:col-span-2">
            <p class="text-xs font-medium text-emerald-800">Name</p>
            <p class="mt-1 font-semibold text-emerald-950">{{ record.name }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p class="text-xs font-medium text-slate-600">Type</p>
            <p class="mt-1 font-semibold text-slate-950">{{ record.type }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p class="text-xs font-medium text-slate-600">License No</p>
            <p class="mt-1 font-semibold text-slate-950">{{ record.license_no }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p class="text-xs font-medium text-slate-600">Valid</p>
            <p class="mt-1 font-semibold text-slate-950 tabular-nums">{{ displayDate(record.valid_from) }} to {{ displayDate(record.valid_to) }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <p class="text-xs font-medium text-slate-600">Status</p>
            <div class="mt-1"><StatusBadge :value="record.status" :label="statusLabel(record.status)" /></div>
          </div>
          <div v-if="record.district_name" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 sm:col-span-2">
            <p class="text-xs font-medium text-slate-600">District</p>
            <p class="mt-1 font-semibold text-slate-950">{{ record.district_name }}</p>
          </div>
        </div>
      </PageSection>
    </div>
  </main>
</template>
