<script setup>
import { onMounted, ref } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api, firstError } from '@/lib/api'

const rows = ref([])
const choices = ref([])
const open = ref(false)
const loadError = ref('')
const formError = ref('')
const notice = ref('')
const form = ref({
  brand_name: '',
  product_id: '',
  source: 'own_import',
  sample_provided: false,
  dpp_registration_no: '',
})

async function load() {
  const { response, payload } = await api('/api/v1/portal/products')

  if (!response.ok) {
    loadError.value = firstError(payload?.errors) || 'Products could not be loaded.'
    return
  }

  rows.value = payload.data || []
  choices.value = payload.meta?.products || []
}

async function addProduct() {
  formError.value = ''
  const { response, payload } = await api('/api/v1/portal/products', {
    method: 'POST',
    body: {
      brand_name: form.value.brand_name,
      product_id: Number(form.value.product_id),
      source: form.value.source,
      sample_provided: form.value.sample_provided,
      dpp_registration_no: form.value.dpp_registration_no || null,
    },
  })

  if (!response.ok) {
    formError.value = firstError(payload?.errors) || 'The product could not be added.'
    return
  }

  open.value = false
  notice.value = 'Product submitted. It stays pending until the Directorate verifies it. To withdraw a product, contact the Directorate.'
  await load()
}

onMounted(load)
</script>

<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Products</h2>
      <Button type="button" @click="open = !open">Add product</Button>
    </div>
    <p class="text-sm text-muted-foreground">To withdraw a product, contact the Directorate.</p>
    <Alert v-if="loadError" variant="destructive"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
    <p v-if="notice" class="text-sm">{{ notice }}</p>
    <form v-if="open" class="grid max-w-lg gap-3 rounded-md border p-4" @submit.prevent="addProduct">
      <div class="grid gap-1">
        <Label for="brand">Brand</Label>
        <Input id="brand" v-model="form.brand_name" required />
      </div>
      <div class="grid gap-1">
        <Label for="generic">Generic</Label>
        <select id="generic" v-model="form.product_id" class="border-input h-9 rounded-md border px-3 text-sm" required>
          <option value="">Choose</option>
          <option v-for="row in choices" :key="row.id" :value="row.id">{{ row.label }}</option>
        </select>
      </div>
      <div class="grid gap-1">
        <Label for="source">Source</Label>
        <select id="source" v-model="form.source" class="border-input h-9 rounded-md border px-3 text-sm">
          <option value="own_import">Own import</option>
          <option value="purchase_agreement">Purchase agreement</option>
        </select>
      </div>
      <div class="grid gap-1">
        <Label for="dpp">DPP registration</Label>
        <Input id="dpp" v-model="form.dpp_registration_no" />
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.sample_provided" type="checkbox">
        Sample provided
      </label>
      <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
      <Button type="submit">Submit</Button>
    </form>
    <div class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-3 py-2">Brand</th>
            <th class="px-3 py-2">Generic</th>
            <th class="px-3 py-2">DPP reg</th>
            <th class="px-3 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-3 py-2">{{ row.brand_name }}</td>
            <td class="px-3 py-2">{{ [row.generic_name, row.concentration, row.formulation].filter(Boolean).join(' ') || '—' }}</td>
            <td class="px-3 py-2">{{ row.dpp_registration_no || '—' }}</td>
            <td class="px-3 py-2">{{ row.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
