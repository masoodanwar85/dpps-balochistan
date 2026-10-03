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
  <div class="grid gap-6">
    <PageSection accent="emerald" eyebrow="Company portal" title="Products">
      <template #actions>
        <Button type="button" size="sm" @click="open = !open">Add product</Button>
      </template>
      <p class="text-sm text-muted-foreground">To withdraw a product, contact the Directorate.</p>
      <Alert v-if="loadError" variant="destructive" class="mt-4"><AlertTitle>{{ loadError }}</AlertTitle></Alert>
      <p v-if="notice" class="mt-2 text-sm">{{ notice }}</p>
    </PageSection>

    <PageSection v-if="open" accent="violet" eyebrow="Catalog" title="Add product">
      <form class="grid max-w-lg gap-3" @submit.prevent="addProduct">
        <div class="dpps-field">
          <Label for="brand">Brand</Label>
          <Input id="brand" v-model="form.brand_name" required />
        </div>
        <div class="dpps-field">
          <Label for="generic">Generic</Label>
          <select id="generic" v-model="form.product_id" class="dpps-select" required>
            <option value="">Choose</option>
            <option v-for="row in choices" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="source">Source</Label>
          <select id="source" v-model="form.source" class="dpps-select">
            <option value="own_import">Own import</option>
            <option value="purchase_agreement">Purchase agreement</option>
          </select>
        </div>
        <div class="dpps-field">
          <Label for="dpp">DPP registration</Label>
          <Input id="dpp" v-model="form.dpp_registration_no" />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.sample_provided" type="checkbox">
          Sample provided
        </label>
        <Alert v-if="formError" variant="destructive"><AlertTitle>{{ formError }}</AlertTitle></Alert>
        <div class="flex gap-2">
          <Button type="submit">Submit</Button>
          <Button type="button" variant="outline" @click="open = false">Cancel</Button>
        </div>
      </form>
    </PageSection>

    <PageSection accent="slate" content-class="px-0 pt-0 pb-0">
      <div class="dpps-table-wrap rounded-none border-0">
        <table class="dpps-table">
          <thead>
            <tr>
              <th>Brand</th>
              <th>Generic</th>
              <th>DPP reg</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="font-medium">{{ row.brand_name }}</td>
              <td>{{ [row.generic_name, row.concentration, row.formulation].filter(Boolean).join(' ') || '—' }}</td>
              <td>{{ row.dpp_registration_no || '—' }}</td>
              <td><StatusBadge :value="row.status" :label="row.status" /></td>
            </tr>
            <tr v-if="rows.length === 0 && !loadError">
              <td class="text-muted-foreground" colspan="4">No products.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </PageSection>
  </div>
</template>
