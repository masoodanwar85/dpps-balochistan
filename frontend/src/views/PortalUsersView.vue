<script setup>
import { onMounted, ref } from 'vue'
import PageSection from '@/components/PageSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const rows = ref([])
const open = ref(false)
const resetId = ref(null)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const form = ref({
  name: '',
  email: '',
  mobile: '',
  password: '',
  password_confirmation: '',
  role: 'Company Staff',
})

async function load() {
  const { response, payload } = await api('/api/v1/portal/users')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Users could not be loaded.'
    return
  }

  rows.value = payload.data || []
}

async function addUser() {
  formError.value = ''
  const { response, payload } = await api('/api/v1/portal/users', { method: 'POST', body: form.value })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The user could not be added.'
    return
  }

  open.value = false
  notice.value = 'User added. They must change the password at the next login.'
  await load()
}

async function deactivate(row) {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/portal/users/${row.id}/deactivate`, { method: 'POST', body: {} })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The user could not be deactivated.'
    return
  }

  notice.value = 'User deactivated.'
  await load()
}

async function resetPassword() {
  formError.value = ''
  const { response, payload } = await api(`/api/v1/portal/users/${resetId.value}/reset-password`, {
    method: 'POST',
    body: {
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The password could not be reset.'
    return
  }

  resetId.value = null
  notice.value = 'Password reset. The user must change it at the next login.'
  await load()
}

onMounted(load)
</script>

<template>
  <div class="grid gap-6">
    <PageSection accent="slate" eyebrow="Company portal" title="Users">
      <template #actions>
        <Button type="button" size="sm" @click="open = !open">Add user</Button>
      </template>
      <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
      <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
      <p v-if="notice" class="text-sm">{{ notice }}</p>
    </PageSection>

    <PageSection v-if="open" accent="violet" eyebrow="Access" title="Add user">
      <form class="grid max-w-lg gap-3" @submit.prevent="addUser">
        <div class="dpps-field"><Label for="name">Name</Label><Input id="name" v-model="form.name" required /></div>
        <div class="dpps-field"><Label for="email">Email</Label><Input id="email" v-model="form.email" type="email" required /></div>
        <div class="dpps-field"><Label for="mobile">Mobile</Label><Input id="mobile" v-model="form.mobile" required /></div>
        <div class="dpps-field"><Label for="password">Temporary password</Label><Input id="password" v-model="form.password" type="password" required /></div>
        <div class="dpps-field"><Label for="confirm">Confirm password</Label><Input id="confirm" v-model="form.password_confirmation" type="password" required /></div>
        <div class="dpps-field">
          <Label for="role">Role</Label>
          <select id="role" v-model="form.role" class="dpps-select">
            <option>Company Admin</option>
            <option>Company Staff</option>
          </select>
        </div>
        <div class="flex gap-2">
          <Button type="submit">Save</Button>
          <Button type="button" variant="outline" @click="open = false">Cancel</Button>
        </div>
      </form>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Active</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.name }}</td>
              <td>{{ row.email }}</td>
              <td>{{ row.role }}</td>
              <td><StatusBadge :value="row.is_active" :label="row.is_active ? 'Yes' : 'No'" /></td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <Button v-if="row.is_active" type="button" variant="outline" size="sm" @click="deactivate(row)">Deactivate</Button>
                  <Button type="button" variant="outline" size="sm" @click="resetId = row.id">Reset password</Button>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground" colspan="5">No users.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>

    <PageSection v-if="resetId" accent="orange" eyebrow="Access" title="Reset password">
      <form class="grid max-w-lg gap-3" @submit.prevent="resetPassword">
        <div class="dpps-field"><Label for="new-password">New temporary password</Label><Input id="new-password" v-model="form.password" type="password" required /></div>
        <div class="dpps-field"><Label for="new-confirm">Confirm password</Label><Input id="new-confirm" v-model="form.password_confirmation" type="password" required /></div>
        <div class="flex gap-2">
          <Button type="submit">Reset</Button>
          <Button type="button" variant="outline" @click="resetId = null">Cancel</Button>
        </div>
      </form>
    </PageSection>
  </div>
</template>
