<script setup>
import { computed, onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const groups = [
  { id: 'licensing', label: 'Licensing' },
  { id: 'alerts', label: 'Alerts' },
  { id: 'numbering', label: 'Numbering' },
  { id: 'general', label: 'Uploads' },
]

const settings = ref([])
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const saving = ref(false)

const grouped = computed(() => groups.map((group) => ({
  ...group,
  settings: settings.value.filter((setting) => setting.group === group.id),
})))

function preview(pattern) {
  const year = String(new Date().getFullYear())

  return String(pattern)
    .replaceAll('{YYYY}', year)
    .replaceAll('{DISTRICT}', 'QTA')
    .replaceAll('{RENEWAL}', '/R1')
    .replaceAll('{C/D}', 'C')
    .replace(/\{SERIAL:(\d+)\}/g, (_, width) => String(46).padStart(Number(width), '0'))
}

async function loadSettings() {
  const { response, payload } = await api('/api/v1/settings')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load settings.'

    return
  }

  settings.value = payload.data
}

async function save() {
  saving.value = true
  formError.value = ''
  notice.value = ''
  const body = { settings: {} }

  for (const setting of settings.value) {
    body.settings[setting.key] = setting.value
  }

  const { response, payload } = await api('/api/v1/settings', { method: 'PUT', body })
  saving.value = false

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'Could not save settings.'

    return
  }

  settings.value = payload.data
  notice.value = 'Settings saved.'
}

onMounted(loadSettings)
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

    <PageSection
      v-for="group in grouped"
      :key="group.id"
      accent="slate"
      eyebrow="Settings"
      :title="group.label"
    >
      <div class="grid max-w-3xl gap-4">
        <div v-for="setting in group.settings" :key="setting.key" class="dpps-field">
          <Label :for="setting.key">{{ setting.label }}</Label>
          <label v-if="setting.data_type === 'boolean'" class="flex items-center gap-2 text-sm" :for="setting.key">
            <input :id="setting.key" v-model="setting.value" type="checkbox">
            {{ setting.value ? 'ON' : 'OFF' }}
          </label>
          <Input
            v-else-if="setting.data_type === 'integer'"
            :id="setting.key"
            v-model.number="setting.value"
            type="number"
            min="1"
            required
          />
          <Input v-else :id="setting.key" v-model="setting.value" required />
          <p v-if="setting.key === 'enforce_document_requirements'" class="text-sm text-muted-foreground">
            ON: a license cannot be issued while any required document is missing or not verified.
            OFF: a license can be issued; it is marked "Documents incomplete" until documents are uploaded and verified.
          </p>
          <p v-else-if="setting.description" class="text-sm text-muted-foreground">{{ setting.description }}</p>
          <p v-if="setting.key.endsWith('_pattern')" class="text-sm">Preview: {{ preview(setting.value) }}</p>
        </div>
      </div>
    </PageSection>

    <PageSection accent="slate" eyebrow="Settings" title="Save">
      <p class="mb-4 text-sm text-muted-foreground">Fees are managed by the system administrator (database only).</p>
      <Button type="submit" :disabled="saving">Save</Button>
    </PageSection>
  </form>
</template>
