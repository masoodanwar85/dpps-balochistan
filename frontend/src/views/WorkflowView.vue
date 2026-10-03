<script setup>
import { computed, onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { api, firstError } from '@/lib/api'

const stages = ref([])
const permissions = ref([])
const entityType = ref('company')
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const saving = ref(false)

const visible = computed(() => stages.value.filter((stage) => stage.entity_type === entityType.value))

async function loadStages() {
  const { response, payload } = await api('/api/v1/workflow-stages')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load workflow stages.'

    return
  }

  stages.value = payload.data
  permissions.value = payload.meta.permissions
}

async function save() {
  saving.value = true
  formError.value = ''
  notice.value = ''
  const { response, payload } = await api('/api/v1/workflow-stages', {
    method: 'PUT',
    body: {
      stages: visible.value.map((stage) => ({
        id: stage.id,
        applies_to: stage.applies_to,
        sla_days: stage.sla_days === '' || stage.sla_days === null ? null : Number(stage.sla_days),
        is_active: stage.is_active,
        is_skippable: stage.is_skippable,
        required_permission: stage.required_permission,
      })),
    },
  })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save workflow stages.'

    return
  }

  stages.value = payload.data
  notice.value = 'Workflow stages saved.'
}

onMounted(loadStages)
</script>

<template>
  <div class="grid gap-6">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="formError" variant="destructive">
      <AlertTitle>{{ formError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <PageSection accent="amber" eyebrow="Settings" title="Workflow stages">
      <template #actions>
        <Button type="button" size="sm" :variant="entityType === 'company' ? 'default' : 'outline'" @click="entityType = 'company'">Companies</Button>
        <Button type="button" size="sm" :variant="entityType === 'dealer' ? 'default' : 'outline'" @click="entityType = 'dealer'">Dealers</Button>
        <Button type="button" size="sm" :disabled="saving" @click="save">Save</Button>
      </template>
      <p class="text-sm text-muted-foreground">Higher approval stays off until you turn it on here. No extra approval screen is included.</p>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Seq</th>
              <th>Stage</th>
              <th>Applies</th>
              <th>SLA days</th>
              <th>Permission</th>
              <th>Active</th>
              <th>Skippable</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="stage in visible" :key="stage.id">
              <td class="tabular-nums">{{ stage.sequence }}</td>
              <td class="font-medium">{{ stage.name }}</td>
              <td>
                <select v-model="stage.applies_to" class="dpps-select" :aria-label="`${stage.name} applies`">
                  <option value="new">New</option>
                  <option value="renewal">Renewal</option>
                  <option value="both">Both</option>
                </select>
              </td>
              <td>
                <input
                  v-model="stage.sla_days"
                  type="number"
                  min="1"
                  class="dpps-select w-20"
                  :aria-label="`${stage.name} SLA days`"
                >
              </td>
              <td>
                <select v-model="stage.required_permission" class="dpps-select max-w-56" :aria-label="`${stage.name} permission`">
                  <option v-for="permission in permissions" :key="permission" :value="permission">{{ permission }}</option>
                </select>
              </td>
              <td>
                <input v-model="stage.is_active" type="checkbox" :aria-label="`${stage.name} active`">
              </td>
              <td>
                <input v-model="stage.is_skippable" type="checkbox" :aria-label="`${stage.name} skippable`">
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>
  </div>
</template>
