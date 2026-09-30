<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
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
  <main class="mx-auto grid min-h-screen max-w-lg content-center gap-4 p-6">
    <p v-if="missing" class="text-lg">Certificate not found.</p>
    <p v-else-if="failed" class="text-lg">The certificate could not be checked.</p>
    <section v-else-if="record" class="grid gap-3 rounded-md border p-5">
      <p v-if="record.banner" class="rounded-md bg-red-100 px-3 py-2 text-sm text-red-800">{{ record.banner }}</p>
      <h1 class="text-lg font-semibold">Verified license</h1>
      <p class="text-sm">Directorate of Plant Protection, Balochistan</p>
      <dl class="grid gap-2 text-sm">
        <div class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>Name</dt>
          <dd>{{ record.name }}</dd>
        </div>
        <div class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>Type</dt>
          <dd>{{ record.type }}</dd>
        </div>
        <div class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>License No</dt>
          <dd>{{ record.license_no }}</dd>
        </div>
        <div class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>Valid</dt>
          <dd>{{ displayDate(record.valid_from) }} to {{ displayDate(record.valid_to) }}</dd>
        </div>
        <div class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>Status</dt>
          <dd>{{ statusLabel(record.status) }}</dd>
        </div>
        <div v-if="record.district_name" class="grid grid-cols-[8rem_1fr] gap-2">
          <dt>District</dt>
          <dd>{{ record.district_name }}</dd>
        </div>
      </dl>
    </section>
  </main>
</template>
