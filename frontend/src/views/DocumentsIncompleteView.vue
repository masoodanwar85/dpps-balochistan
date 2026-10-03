<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
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
  <div class="grid gap-6">
    <PageSection accent="rose" eyebrow="Compliance" title="Documents incomplete">
      <template #actions>
        <Button type="button" variant="outline" size="sm" @click="router.push({ name: 'dashboard' })">Back</Button>
      </template>
      <Alert v-if="loadError" variant="destructive">
        <AlertTitle>{{ loadError }}</AlertTitle>
      </Alert>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Applicant</th>
              <th>License No</th>
              <th>Valid to</th>
              <th>Documents</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.applicant_name }}</td>
              <td>{{ row.license_no }}</td>
              <td class="tabular-nums">{{ displayDate(row.valid_to) }}</td>
              <td><StatusBadge value="incomplete" label="Incomplete" /></td>
              <td class="text-right"><Button type="button" variant="outline" size="sm" @click="openRow(row)">View</Button></td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground" colspan="5">No licenses with incomplete documents.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>
  </div>
</template>
