<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const users = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const options = ref({ staff_roles: [], company_roles: [], districts: [], companies: [] })
const roles = ref([])
const modules = ref([])
const loadError = ref('')
const formError = ref('')
const resetError = ref('')
const matrixError = ref('')
const notice = ref('')
const showForm = ref(false)
const editingId = ref(null)
const resetId = ref(null)
const saving = ref(false)
const selectedRoleId = ref(null)
const selectedPermissions = ref([])

const filters = reactive({
  search: '',
  user_type: '',
  role: '',
  is_active: '',
  page: 1,
})

const form = reactive(emptyForm())
const resetForm = reactive({ password: '', password_confirmation: '' })

const roleChoices = computed(() => (
  form.user_type === 'company' ? options.value.company_roles : options.value.staff_roles
))

const showDistricts = computed(() => form.roles.includes('District Officer'))

const selectedRole = computed(() => roles.value.find((role) => role.id === selectedRoleId.value) ?? null)

function emptyForm() {
  return {
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
    user_type: 'staff',
    company_id: '',
    is_active: true,
    roles: [],
    district_ids: [],
  }
}

function formatLogin(value) {
  if (!value) {
    return '—'
  }

  return value.replace('T', ' ').slice(0, 16)
}

function scopeLabel(user) {
  if (user.user_type === 'company') {
    return user.company_name || '—'
  }

  if (user.districts.length) {
    return user.districts.map((district) => district.name).join(', ')
  }

  return '—'
}

async function loadUsers() {
  const params = new URLSearchParams({ page: String(filters.page), per_page: '15' })

  if (filters.search) {
    params.set('search', filters.search)
  }

  if (filters.user_type) {
    params.set('filter[user_type]', filters.user_type)
  }

  if (filters.role) {
    params.set('filter[role]', filters.role)
  }

  if (filters.is_active !== '') {
    params.set('filter[is_active]', filters.is_active)
  }

  const { response, payload } = await api(`/api/v1/users?${params}`)

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Could not load users.'

    return
  }

  users.value = payload.data
  meta.value = payload.meta
}

async function loadScreen() {
  loadError.value = ''

  const [optionsResult, rolesResult, permissionsResult] = await Promise.all([
    auth.can('users.manage') ? api('/api/v1/users/options') : Promise.resolve(null),
    api('/api/v1/roles'),
    api('/api/v1/permissions'),
  ])

  if (optionsResult?.response.ok) {
    options.value = optionsResult.payload.data
  }

  if (rolesResult.response.ok) {
    roles.value = rolesResult.payload.data
    selectedRoleId.value = roles.value[0]?.id ?? null
    syncSelectedPermissions()
  }

  if (permissionsResult.response.ok) {
    modules.value = permissionsResult.payload.data.modules
  }

  if (auth.can('users.manage')) {
    await loadUsers()
  }
}

function syncSelectedPermissions() {
  selectedPermissions.value = [...(selectedRole.value?.permissions ?? [])]
}

function chooseRole(id) {
  selectedRoleId.value = id
  syncSelectedPermissions()
  matrixError.value = ''
}

function togglePermission(key, checked) {
  if (checked) {
    selectedPermissions.value = [...selectedPermissions.value, key]

    return
  }

  selectedPermissions.value = selectedPermissions.value.filter((item) => item !== key)
}

function startCreate() {
  Object.assign(form, emptyForm())
  editingId.value = null
  showForm.value = true
  formError.value = ''
  resetId.value = null
}

function startEdit(user) {
  Object.assign(form, {
    name: user.name,
    email: user.email,
    mobile: user.mobile,
    password: '',
    password_confirmation: '',
    user_type: user.user_type,
    company_id: user.company_id ?? '',
    is_active: user.is_active,
    roles: [...user.roles],
    district_ids: user.districts.map((district) => district.id),
  })
  editingId.value = user.id
  showForm.value = true
  formError.value = ''
  resetId.value = null
}

function onTypeChange() {
  form.roles = []
  form.company_id = ''
  form.district_ids = []
}

function toggleRole(role, checked) {
  form.roles = checked ? [...form.roles, role] : form.roles.filter((item) => item !== role)

  if (!form.roles.includes('District Officer')) {
    form.district_ids = []
  }
}

function toggleDistrict(id, checked) {
  form.district_ids = checked
    ? [...form.district_ids, id]
    : form.district_ids.filter((item) => item !== id)
}

function payload() {
  return {
    name: form.name,
    email: form.email,
    mobile: form.mobile,
    password: form.password,
    password_confirmation: form.password_confirmation,
    user_type: form.user_type,
    company_id: form.user_type === 'company' ? Number(form.company_id) : null,
    is_active: form.is_active,
    roles: form.roles,
    district_ids: showDistricts.value ? form.district_ids : [],
  }
}

async function saveUser() {
  saving.value = true
  formError.value = ''
  const body = payload()

  if (editingId.value) {
    delete body.password
    delete body.password_confirmation
  }

  const { response, payload: result } = await api(
    editingId.value ? `/api/v1/users/${editingId.value}` : '/api/v1/users',
    { method: editingId.value ? 'PUT' : 'POST', body },
  )

  saving.value = false

  if (!response.ok) {
    formError.value = firstError(result?.errors) || 'Could not save the user.'

    return
  }

  showForm.value = false
  notice.value = editingId.value ? 'User updated.' : 'User created. They must change the password at next login.'
  await loadUsers()
}

function startReset(user) {
  resetId.value = user.id
  resetForm.password = ''
  resetForm.password_confirmation = ''
  resetError.value = ''
  showForm.value = false
}

async function saveReset() {
  saving.value = true
  resetError.value = ''
  const { response, payload: result } = await api(`/api/v1/users/${resetId.value}/reset-password`, {
    method: 'POST',
    body: { ...resetForm },
  })
  saving.value = false

  if (!response.ok) {
    resetError.value = firstError(result?.errors) || 'Could not reset the password.'

    return
  }

  resetId.value = null
  notice.value = 'Password reset. The user must change it at next login.'
  await loadUsers()
}

async function saveMatrix() {
  saving.value = true
  matrixError.value = ''
  const { response, payload: result } = await api(`/api/v1/roles/${selectedRoleId.value}`, {
    method: 'PUT',
    body: { permissions: selectedPermissions.value },
  })
  saving.value = false

  if (!response.ok) {
    matrixError.value = firstError(result?.errors) || 'Could not save the role.'

    return
  }

  const index = roles.value.findIndex((role) => role.id === selectedRoleId.value)
  roles.value[index] = result.data
  notice.value = 'Role permissions saved.'
  await auth.fetchUser(true)
}

function applyFilters() {
  filters.page = 1
  loadUsers()
}

function changePage(page) {
  filters.page = page
  loadUsers()
}

onMounted(loadScreen)
</script>

<template>
  <div class="grid gap-8">
    <Alert v-if="loadError" variant="destructive">
      <AlertTitle>{{ loadError }}</AlertTitle>
    </Alert>
    <Alert v-if="notice">
      <AlertTitle>{{ notice }}</AlertTitle>
    </Alert>

    <section v-if="auth.can('users.manage')" class="grid gap-4">
      <div class="flex flex-wrap items-end gap-3">
        <h2 class="text-lg font-semibold">Users</h2>
        <Button @click="startCreate">New User</Button>
      </div>

      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="applyFilters">
        <div class="grid gap-1">
          <Label for="user-search">Search</Label>
          <Input id="user-search" v-model="filters.search" placeholder="Name, email, or mobile" />
        </div>
        <div class="grid gap-1">
          <Label for="user-type">Type</Label>
          <select id="user-type" v-model="filters.user_type" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
            <option value="">All</option>
            <option value="staff">Staff</option>
            <option value="company">Company</option>
          </select>
        </div>
        <div class="grid gap-1">
          <Label for="user-role">Role</Label>
          <select id="user-role" v-model="filters.role" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
            <option value="">All</option>
            <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
          </select>
        </div>
        <div class="grid gap-1">
          <Label for="user-active">Active</Label>
          <select id="user-active" v-model="filters.is_active" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
            <option value="">All</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
        <Button type="submit" variant="outline">Apply</Button>
      </form>

      <form v-if="showForm" class="grid gap-4 rounded-xl border bg-card p-4" @submit.prevent="saveUser">
        <h3 class="font-medium">{{ editingId ? 'Edit user' : 'New user' }}</h3>
        <Alert v-if="formError" variant="destructive">
          <AlertTitle>{{ formError }}</AlertTitle>
        </Alert>
        <div class="grid gap-3 md:grid-cols-2">
          <div class="grid gap-1">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" required />
          </div>
          <div class="grid gap-1">
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" required />
          </div>
          <div class="grid gap-1">
            <Label for="mobile">Mobile</Label>
            <Input id="mobile" v-model="form.mobile" required />
          </div>
          <div class="grid gap-1">
            <Label for="type">Type</Label>
            <select id="type" v-model="form.user_type" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm" @change="onTypeChange">
              <option value="staff">Staff</option>
              <option value="company">Company</option>
            </select>
          </div>
          <div v-if="form.user_type === 'company'" class="grid gap-1">
            <Label for="company">Company</Label>
            <select id="company" v-model="form.company_id" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm" required>
              <option value="">Select a company</option>
              <option v-for="company in options.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
          </div>
          <template v-if="!editingId">
            <div class="grid gap-1">
              <Label for="temp-password">Temporary password</Label>
              <Input id="temp-password" v-model="form.password" type="password" autocomplete="new-password" required />
            </div>
            <div class="grid gap-1">
              <Label for="temp-password-confirm">Confirm temporary password</Label>
              <Input id="temp-password-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </div>
          </template>
        </div>
        <fieldset class="grid gap-2">
          <legend class="text-sm font-medium">Role(s)</legend>
          <label v-for="role in roleChoices" :key="role" class="flex items-center gap-2 text-sm">
            <input type="checkbox" :checked="form.roles.includes(role)" @change="toggleRole(role, $event.target.checked)">
            {{ role }}
          </label>
        </fieldset>
        <fieldset v-if="showDistricts" class="grid max-h-48 gap-2 overflow-auto">
          <legend class="text-sm font-medium">Districts</legend>
          <label v-for="district in options.districts" :key="district.id" class="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              :checked="form.district_ids.includes(district.id)"
              @change="toggleDistrict(district.id, $event.target.checked)"
            >
            {{ district.name }} ({{ district.code }})
          </label>
        </fieldset>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_active" type="checkbox">
          Active
        </label>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Save</Button>
          <Button type="button" variant="outline" @click="showForm = false">Cancel</Button>
        </div>
      </form>

      <form v-if="resetId" class="grid max-w-md gap-3 rounded-xl border bg-card p-4" @submit.prevent="saveReset">
        <h3 class="font-medium">Reset password</h3>
        <p class="text-sm text-muted-foreground">This sets a temporary password. The user must change it at next login.</p>
        <Alert v-if="resetError" variant="destructive">
          <AlertTitle>{{ resetError }}</AlertTitle>
        </Alert>
        <div class="grid gap-1">
          <Label for="reset-password">Temporary password</Label>
          <Input id="reset-password" v-model="resetForm.password" type="password" required />
        </div>
        <div class="grid gap-1">
          <Label for="reset-password-confirm">Confirm temporary password</Label>
          <Input id="reset-password-confirm" v-model="resetForm.password_confirmation" type="password" required />
        </div>
        <div class="flex gap-2">
          <Button type="submit" :disabled="saving">Reset password</Button>
          <Button type="button" variant="outline" @click="resetId = null">Cancel</Button>
        </div>
      </form>

      <div class="overflow-x-auto rounded-xl border bg-card">
        <table class="w-full text-left text-sm">
          <thead class="border-b text-muted-foreground">
            <tr>
              <th class="px-3 py-2 font-medium">Name</th>
              <th class="px-3 py-2 font-medium">Email</th>
              <th class="px-3 py-2 font-medium">Type</th>
              <th class="px-3 py-2 font-medium">Role(s)</th>
              <th class="px-3 py-2 font-medium">Company/District</th>
              <th class="px-3 py-2 font-medium">Last login</th>
              <th class="px-3 py-2 font-medium">Active</th>
              <th class="px-3 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-b last:border-0">
              <td class="px-3 py-2">{{ user.name }}</td>
              <td class="px-3 py-2">{{ user.email }}</td>
              <td class="px-3 py-2">{{ user.user_type }}</td>
              <td class="px-3 py-2">{{ user.roles.join(', ') }}</td>
              <td class="px-3 py-2">{{ scopeLabel(user) }}</td>
              <td class="px-3 py-2">{{ formatLogin(user.last_login_at) }}</td>
              <td class="px-3 py-2">{{ user.is_active ? 'Yes' : 'No' }}</td>
              <td class="px-3 py-2">
                <button type="button" class="underline" @click="startEdit(user)">Edit</button>
                <button type="button" class="ml-3 underline" @click="startReset(user)">Reset password</button>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="8" class="px-3 py-6 text-muted-foreground">No users match these filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <Button type="button" variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Previous</Button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <Button type="button" variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next</Button>
      </div>
    </section>

    <section v-if="auth.can('roles.manage')" class="grid gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-semibold">Roles</h2>
        <select
          class="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
          :value="selectedRoleId"
          @change="chooseRole(Number($event.target.value))"
        >
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <Button :disabled="saving || !selectedRoleId" @click="saveMatrix">Save</Button>
      </div>
      <Alert v-if="matrixError" variant="destructive">
        <AlertTitle>{{ matrixError }}</AlertTitle>
      </Alert>
      <div class="overflow-x-auto rounded-xl border bg-card">
        <table class="w-full text-left text-sm">
          <thead class="border-b text-muted-foreground">
            <tr>
              <th class="px-3 py-2 font-medium">Module</th>
              <th class="px-3 py-2 font-medium">view</th>
              <th class="px-3 py-2 font-medium">create</th>
              <th class="px-3 py-2 font-medium">update</th>
              <th class="px-3 py-2 font-medium">delete</th>
              <th class="px-3 py-2 font-medium">other</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="module in modules" :key="module.module" class="border-b last:border-0">
              <td class="px-3 py-2">{{ module.module }}</td>
              <td v-for="column in ['view', 'create', 'update', 'delete']" :key="column" class="px-3 py-2">
                <input
                  v-if="module[column]"
                  type="checkbox"
                  :checked="selectedPermissions.includes(module[column])"
                  :aria-label="`${module.module} ${column}`"
                  @change="togglePermission(module[column], $event.target.checked)"
                >
                <span v-else>—</span>
              </td>
              <td class="px-3 py-2">
                <div v-if="module.other.length" class="flex flex-wrap gap-3">
                  <label v-for="item in module.other" :key="item.key" class="flex items-center gap-1">
                    <input
                      type="checkbox"
                      :checked="selectedPermissions.includes(item.key)"
                      @change="togglePermission(item.key, $event.target.checked)"
                    >
                    {{ item.label }}
                  </label>
                </div>
                <span v-else>—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
