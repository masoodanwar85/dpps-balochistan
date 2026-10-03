<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PageSection from '@/components/PageSection.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const canUpdate = computed(() => auth.can('persons.update'))

const cnic = ref('')
const searching = ref(false)
const searchError = ref('')
const notFound = ref(false)
const profile = ref(null)
const form = ref(null)
const tab = ref('qualifications')
const saving = ref(false)
const formError = ref('')
const warnings = ref([])
const warningReason = ref('')
const notice = ref('')

function emptyToNull(value) {
  const text = typeof value === 'string' ? value.trim() : value

  return text === '' || text === undefined ? null : text
}

function blankForm(person) {
  return {
    full_name: person.full_name ?? '',
    father_name: person.father_name ?? '',
    gender: person.gender ?? '',
    date_of_birth: person.date_of_birth ?? '',
    mobile: person.mobile ?? '',
    alt_mobile: person.alt_mobile ?? '',
    email: person.email ?? '',
    address: person.address ?? '',
    cnic: person.cnic ?? '',
    qualifications: (person.qualifications ?? []).map((row) => ({
      id: row.id,
      qualification_id: row.qualification_id,
      institution: row.institution ?? '',
      passing_year: row.passing_year ?? '',
    })),
  }
}

function applyPerson(person) {
  profile.value = person
  form.value = blankForm(person)
  warnings.value = []
  warningReason.value = ''
  notice.value = ''
  formError.value = ''
  tab.value = 'qualifications'
}

function roleLine(role) {
  const end = role.current ? 'present' : `${role.end_date} (ended)`

  return `${role.start_date} → ${end}`
}

async function search() {
  searching.value = true
  searchError.value = ''
  notFound.value = false
  notice.value = ''
  const { response, payload } = await api(`/api/v1/persons/lookup?cnic=${encodeURIComponent(cnic.value)}`)
  searching.value = false

  if (!response.ok) {
    profile.value = null
    searchError.value = firstError(payload?.errors) || 'Could not look up this CNIC.'

    return
  }

  if (!payload.data.found) {
    profile.value = null
    notFound.value = true

    return
  }

  applyPerson(payload.data.person)
}

function addQualification() {
  form.value.qualifications.push({
    id: null,
    qualification_id: '',
    institution: '',
    passing_year: '',
  })
}

function removeQualification(index) {
  form.value.qualifications.splice(index, 1)
}

function payload(confirm) {
  const digits = String(form.value.cnic ?? '').replace(/\D/g, '')
  const body = {
    full_name: form.value.full_name,
    father_name: emptyToNull(form.value.father_name),
    gender: emptyToNull(form.value.gender),
    date_of_birth: emptyToNull(form.value.date_of_birth),
    mobile: form.value.mobile,
    alt_mobile: emptyToNull(form.value.alt_mobile),
    email: emptyToNull(form.value.email),
    address: emptyToNull(form.value.address),
    cnic: digits || null,
    cnic_pending: digits === '',
    qualifications: form.value.qualifications.map((row) => ({
      id: row.id,
      qualification_id: row.qualification_id === '' ? null : Number(row.qualification_id),
      institution: emptyToNull(row.institution),
      passing_year: row.passing_year === '' || row.passing_year === null ? null : Number(row.passing_year),
    })),
  }

  if (confirm) {
    body.confirm_warnings = true
    body.warning_reason = warningReason.value
  }

  return body
}

async function save(confirm = false) {
  formError.value = ''
  notice.value = ''

  if (!profile.value.cnic_pending && !String(form.value.cnic ?? '').replace(/\D/g, '')) {
    formError.value = 'CNIC must be exactly 13 digits.'

    return
  }

  if (form.value.qualifications.some((row) => row.qualification_id === '' || row.qualification_id === null)) {
    formError.value = 'Choose a qualification or remove the empty row.'

    return
  }

  saving.value = true
  const { response, payload: result } = await api(`/api/v1/persons/${profile.value.id}`, {
    method: 'PUT',
    body: payload(confirm),
  })
  saving.value = false

  if (response.status === 409) {
    warnings.value = result?.warnings ?? []

    return
  }

  if (!response.ok) {
    formError.value = firstError(result?.errors) || 'Could not save this person.'

    return
  }

  warnings.value = []
  warningReason.value = ''
  applyPerson(result.data)
  notice.value = 'Person saved.'
}

onMounted(() => {
  if (route.query.cnic) {
    cnic.value = String(route.query.cnic)
    search()
  }
})
</script>

<template>
  <div class="grid gap-6">
    <PageSection accent="sky" eyebrow="People" title="Persons">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
        <div class="dpps-field">
          <Label for="cnic-search">CNIC</Label>
          <Input id="cnic-search" v-model="cnic" class="w-64" placeholder="54400-1111111-1" aria-label="CNIC" />
        </div>
        <Button type="submit" :disabled="searching">Check</Button>
      </form>
      <Alert v-if="searchError" variant="destructive" class="mt-4">
        <AlertTitle>{{ searchError }}</AlertTitle>
      </Alert>
      <Alert v-if="notFound" class="mt-4">
        <AlertTitle>No person with this CNIC.</AlertTitle>
      </Alert>
    </PageSection>

    <template v-if="profile">
      <PageSection accent="sky" eyebrow="Profile" :title="profile.full_name">
        <p class="text-sm text-muted-foreground">
          CNIC {{ profile.cnic_display || 'Pending' }} · Mobile {{ profile.mobile }}
        </p>
      </PageSection>

      <Alert v-if="profile.cnic_pending">
        <AlertTitle>CNIC pending. This person does not count as verified staff.</AlertTitle>
      </Alert>
      <Alert v-if="profile.blocks_license" variant="destructive">
        <AlertTitle>
          No license can be issued while this person is current technical staff, CEO, director, or a dealer owner.
        </AlertTitle>
      </Alert>
      <Alert v-for="block in profile.blocks" :key="`${block.rule}-${block.for}-${block.message}`" variant="destructive">
        <AlertTitle>{{ block.rule }}: {{ block.message }}</AlertTitle>
      </Alert>
      <Alert v-if="formError" variant="destructive">
        <AlertTitle>{{ formError }}</AlertTitle>
      </Alert>
      <Alert v-if="notice">
        <AlertTitle>{{ notice }}</AlertTitle>
      </Alert>

      <PageSection accent="slate" eyebrow="History" title="Roles across the system" content-class="px-0 pt-0 pb-0">
        <div class="dpps-table-wrap rounded-none border-0">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>Role</th>
                <th>Organisation</th>
                <th>From → to</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="profile.roles.length === 0">
                <td class="text-muted-foreground" colspan="3">No roles recorded.</td>
              </tr>
              <tr v-for="(role, index) in profile.roles" :key="`${role.kind}-${role.organisation}-${index}`">
                <td>{{ role.label }}</td>
                <td>{{ role.organisation }}</td>
                <td>{{ roleLine(role) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>

      <PageSection v-if="canUpdate && form" accent="sky" eyebrow="Details" title="Edit person">
      <form class="grid gap-4" @submit.prevent="save(false)">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="dpps-field">
            <Label for="full-name">Full name</Label>
            <Input id="full-name" v-model="form.full_name" required />
          </div>
          <div class="dpps-field">
            <Label for="person-cnic">CNIC</Label>
            <Input id="person-cnic" v-model="form.cnic" aria-label="Profile CNIC" />
          </div>
          <div class="dpps-field">
            <Label for="father-name">Father name</Label>
            <Input id="father-name" v-model="form.father_name" />
          </div>
          <div class="dpps-field">
            <Label for="gender">Gender</Label>
            <select id="gender" v-model="form.gender" class="dpps-select" aria-label="Gender">
              <option value="">Not set</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="dpps-field">
            <Label for="date-of-birth">Date of birth</Label>
            <Input id="date-of-birth" v-model="form.date_of_birth" type="date" />
          </div>
          <div class="dpps-field">
            <Label for="mobile">Mobile</Label>
            <Input id="mobile" v-model="form.mobile" required />
          </div>
          <div class="dpps-field">
            <Label for="alt-mobile">Alt mobile</Label>
            <Input id="alt-mobile" v-model="form.alt_mobile" />
          </div>
          <div class="dpps-field">
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" />
          </div>
          <div class="dpps-field sm:col-span-2">
            <Label for="address">Address</Label>
            <textarea id="address" v-model="form.address" rows="2" class="dpps-textarea" />
          </div>
        </div>
        <p class="text-sm text-muted-foreground">
          {{ profile.photo_path ? `Photo: ${profile.photo_path}` : 'No photo. The photo file is uploaded with documents in a later step.' }}
        </p>

        <div class="flex gap-2">
          <Button type="button" :variant="tab === 'qualifications' ? 'default' : 'outline'" @click="tab = 'qualifications'">Qualifications</Button>
          <Button type="button" :variant="tab === 'documents' ? 'default' : 'outline'" @click="tab = 'documents'">Documents</Button>
          <Button type="button" :variant="tab === 'activity' ? 'default' : 'outline'" @click="tab = 'activity'">Activity</Button>
        </div>

        <div v-if="tab === 'qualifications'" class="grid gap-3">
          <p class="text-sm text-muted-foreground">Qualifications are optional.</p>
          <div v-for="(row, index) in form.qualifications" :key="row.id ?? `new-${index}`" class="grid gap-2 rounded-lg border bg-muted/30 p-3 sm:grid-cols-4">
            <select v-model="row.qualification_id" class="dpps-select" :aria-label="`Qualification ${index + 1}`">
              <option value="">Choose</option>
              <option v-for="choice in profile.qualification_choices" :key="choice.id" :value="choice.id">{{ choice.name }}</option>
            </select>
            <Input v-model="row.institution" placeholder="Institution" :aria-label="`Institution ${index + 1}`" />
            <Input v-model="row.passing_year" type="number" placeholder="Year" :aria-label="`Passing year ${index + 1}`" />
            <Button type="button" variant="outline" @click="removeQualification(index)">Remove</Button>
          </div>
          <div>
            <Button type="button" variant="outline" @click="addQualification">Add qualification</Button>
          </div>
        </div>

        <div v-else-if="tab === 'documents'" class="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
          Documents are added in a later step.
        </div>

        <div v-else class="dpps-table-wrap">
          <table class="dpps-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Action</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="profile.activity.length === 0">
                <td class="text-muted-foreground" colspan="3">No activity yet.</td>
              </tr>
              <tr v-for="row in profile.activity" :key="row.id">
                <td>{{ row.created_at }}</td>
                <td>{{ row.action }}</td>
                <td>{{ row.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <Alert v-if="warnings.length">
          <AlertTitle>{{ warnings.join(' ') }} Enter a reason to continue.</AlertTitle>
        </Alert>
        <div v-if="warnings.length" class="grid max-w-lg gap-1.5">
          <Label for="warning-reason">Reason</Label>
          <textarea id="warning-reason" v-model="warningReason" rows="2" class="dpps-textarea" aria-label="Warning reason" />
        </div>

        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button v-if="warnings.length" type="button" variant="outline" :disabled="saving" @click="save(true)">Confirm and save</Button>
        </div>
      </form>
      </PageSection>

      <PageSection v-else accent="sky" eyebrow="Details" title="Person details">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Father name</dt><dd class="font-medium">{{ profile.father_name || '—' }}</dd></div>
          <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Gender</dt><dd class="font-medium">{{ profile.gender || '—' }}</dd></div>
          <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Date of birth</dt><dd class="font-medium">{{ profile.date_of_birth || '—' }}</dd></div>
          <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Alt mobile</dt><dd class="font-medium">{{ profile.alt_mobile || '—' }}</dd></div>
          <div class="rounded-lg border bg-muted/30 px-3 py-2"><dt class="text-xs text-muted-foreground">Email</dt><dd class="font-medium">{{ profile.email || '—' }}</dd></div>
          <div class="rounded-lg border bg-muted/30 px-3 py-2 sm:col-span-2"><dt class="text-xs text-muted-foreground">Address</dt><dd class="font-medium">{{ profile.address || '—' }}</dd></div>
        </dl>
        <div class="flex gap-2">
          <Button type="button" :variant="tab === 'qualifications' ? 'default' : 'outline'" @click="tab = 'qualifications'">Qualifications</Button>
          <Button type="button" :variant="tab === 'documents' ? 'default' : 'outline'" @click="tab = 'documents'">Documents</Button>
          <Button type="button" :variant="tab === 'activity' ? 'default' : 'outline'" @click="tab = 'activity'">Activity</Button>
        </div>
        <div v-if="tab === 'qualifications'" class="text-sm">
          <p v-if="profile.qualifications.length === 0" class="text-muted-foreground">No qualifications.</p>
          <ul v-else class="grid gap-1">
            <li v-for="row in profile.qualifications" :key="row.id">
              {{ row.qualification_name }}<span v-if="row.institution">, {{ row.institution }}</span><span v-if="row.passing_year"> ({{ row.passing_year }})</span>
            </li>
          </ul>
        </div>
        <div v-else-if="tab === 'documents'" class="mt-4 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
          Documents are added in a later step.
        </div>
        <div v-else class="mt-4 dpps-table-wrap">
          <table class="dpps-table">
            <tbody>
              <tr v-if="profile.activity.length === 0">
                <td class="text-muted-foreground">No activity yet.</td>
              </tr>
              <tr v-for="row in profile.activity" :key="row.id">
                <td>{{ row.created_at }}</td>
                <td>{{ row.action }}</td>
                <td>{{ row.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageSection>
    </template>
  </div>
</template>
