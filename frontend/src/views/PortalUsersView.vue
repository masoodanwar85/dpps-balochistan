<script setup>
import { onMounted, ref } from 'vue'
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
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Users</h2>
      <Button type="button" @click="open = !open">Add user</Button>
    </div>
    <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
    <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
    <p v-if="notice" class="text-sm">{{ notice }}</p>
    <form v-if="open" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="addUser">
      <div class="grid gap-1"><Label for="name">Name</Label><Input id="name" v-model="form.name" required /></div>
      <div class="grid gap-1"><Label for="email">Email</Label><Input id="email" v-model="form.email" type="email" required /></div>
      <div class="grid gap-1"><Label for="mobile">Mobile</Label><Input id="mobile" v-model="form.mobile" required /></div>
      <div class="grid gap-1"><Label for="password">Temporary password</Label><Input id="password" v-model="form.password" type="password" required /></div>
      <div class="grid gap-1"><Label for="confirm">Confirm password</Label><Input id="confirm" v-model="form.password_confirmation" type="password" required /></div>
      <div class="grid gap-1">
        <Label for="role">Role</Label>
        <select id="role" v-model="form.role" class="border-input h-9 rounded-md border px-3 text-sm">
          <option>Company Admin</option>
          <option>Company Staff</option>
        </select>
      </div>
      <Button type="submit">Save</Button>
    </form>
    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Name</th>
            <th class="px-3 py-2">Email</th>
            <th class="px-3 py-2">Role</th>
            <th class="px-3 py-2">Active</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.name }}</td>
            <td class="px-3 py-2">{{ row.email }}</td>
            <td class="px-3 py-2">{{ row.role }}</td>
            <td class="px-3 py-2">{{ row.is_active ? 'Yes' : 'No' }}</td>
            <td class="px-3 py-2">
              <Button v-if="row.is_active" type="button" variant="outline" @click="deactivate(row)">Deactivate</Button>
              <Button type="button" variant="outline" class="ml-2" @click="resetId = row.id">Reset password</Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <form v-if="resetId" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="resetPassword">
      <div class="grid gap-1"><Label for="new-password">New temporary password</Label><Input id="new-password" v-model="form.password" type="password" required /></div>
      <div class="grid gap-1"><Label for="new-confirm">Confirm password</Label><Input id="new-confirm" v-model="form.password_confirmation" type="password" required /></div>
      <Button type="submit">Reset</Button>
    </form>
  </div>
</template>
