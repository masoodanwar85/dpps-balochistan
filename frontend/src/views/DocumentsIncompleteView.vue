<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { api, firstError } from '@/lib/api'

const router = useRouter()
const rows = ref([])
const loadError = ref('')

function displayDate(value) {
  if (!value) {
    return '—'
  }

  const [year, month, day] = String(value).slice(0, 10).split('-')

  return day && month && year ? `${day}-${month}-${year}` : value
}

function openRow(row) {
  if (row.application_id) {
    router.push({ name: 'application-detail', params: { id: row.application_id } })
    return
  }

  const name = row.licensable_type === 'dealer' ? 'dealer-profile' : 'company-profile'
  router.push({ name, params: { id: row.licensable_id } })
}

onMounted(async () => {
  const { response, payload } = await api('/api/v1/applications/documents-incomplete')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'The list could not be loaded.'
    return
  }

  rows.value = payload.data || []
})
</script>

<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Documents incomplete</h2>
      <Button type="button" variant="outline" @click="router.push({ name: 'dashboard' })">Back</Button>
    </div>
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Applicant</th>
            <th class="px-3 py-2">License No</th>
            <th class="px-3 py-2">Valid to</th>
            <th class="px-3 py-2">Documents</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.applicant_name }}</td>
            <td class="px-3 py-2">{{ row.license_no }}</td>
            <td class="px-3 py-2">{{ displayDate(row.valid_to) }}</td>
            <td class="px-3 py-2">Incomplete</td>
            <td class="px-3 py-2"><Button type="button" variant="outline" @click="openRow(row)">View</Button></td>
          </tr>
          <tr v-if="rows.length === 0 && !loadError">
            <td class="text-muted-foreground px-3 py-4" colspan="5">No licenses with incomplete documents.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
