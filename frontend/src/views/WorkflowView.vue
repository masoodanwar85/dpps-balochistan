<script setup>
import { computed, onMounted, ref } from 'vue'
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
  <div class="grid gap-4">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="formError" variant="destructive">
      <AlertTitle>{{ formError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <div class="flex gap-2">
      <Button type="button" :variant="entityType === 'company' ? 'default' : 'outline'" @click="entityType = 'company'">Companies</Button>
      <Button type="button" :variant="entityType === 'dealer' ? 'default' : 'outline'" @click="entityType = 'dealer'">Dealers</Button>
    </div>

    <div class="overflow-x-auto rounded-xl border bg-card">
      <table class="w-full text-left text-sm">
        <thead class="border-b text-muted-foreground">
          <tr>
            <th class="px-3 py-2 font-medium">Seq</th>
            <th class="px-3 py-2 font-medium">Stage</th>
            <th class="px-3 py-2 font-medium">Applies</th>
            <th class="px-3 py-2 font-medium">SLA days</th>
            <th class="px-3 py-2 font-medium">Permission</th>
            <th class="px-3 py-2 font-medium">Active</th>
            <th class="px-3 py-2 font-medium">Skippable</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="stage in visible" :key="stage.id" class="border-b last:border-0">
            <td class="px-3 py-2">{{ stage.sequence }}</td>
            <td class="px-3 py-2">{{ stage.name }}</td>
            <td class="px-3 py-2">
              <select v-model="stage.applies_to" class="border-input h-9 rounded-md border bg-transparent px-2 text-sm" :aria-label="`${stage.name} applies`">
                <option value="new">New</option>
                <option value="renewal">Renewal</option>
                <option value="both">Both</option>
              </select>
            </td>
            <td class="px-3 py-2">
              <input
                v-model="stage.sla_days"
                type="number"
                min="1"
                class="border-input h-9 w-20 rounded-md border bg-transparent px-2 text-sm"
                :aria-label="`${stage.name} SLA days`"
              >
            </td>
            <td class="px-3 py-2">
              <select v-model="stage.required_permission" class="border-input h-9 max-w-56 rounded-md border bg-transparent px-2 text-sm" :aria-label="`${stage.name} permission`">
                <option v-for="permission in permissions" :key="permission" :value="permission">{{ permission }}</option>
              </select>
            </td>
            <td class="px-3 py-2">
              <input v-model="stage.is_active" type="checkbox" :aria-label="`${stage.name} active`">
            </td>
            <td class="px-3 py-2">
              <input v-model="stage.is_skippable" type="checkbox" :aria-label="`${stage.name} skippable`">
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="text-sm text-muted-foreground">Higher approval stays off until you turn it on here. No extra approval screen is included.</p>
    <div>
      <Button type="button" :disabled="saving" @click="save">Save</Button>
    </div>
  </div>
</template>
