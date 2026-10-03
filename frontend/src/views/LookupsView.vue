<script setup>
import { computed, onMounted, ref } from 'vue'
import CatalogPanel from '@/components/CatalogPanel.vue'
import PageSection from '@/components/PageSection.vue'
import { api } from '@/lib/api'

const documentCategories = [
  ['application', 'Application'],
  ['cnic', 'CNIC'],
  ['license', 'License'],
  ['challan', 'Challan'],
  ['inspection', 'Inspection'],
  ['correspondence', 'Correspondence'],
  ['agreement', 'Agreement'],
  ['qualification', 'Qualification'],
  ['other', 'Other'],
].map(([value, label]) => ({ value, label }))

const appliesTo = [
  ['company', 'Company'],
  ['dealer', 'Dealer'],
  ['person', 'Person'],
  ['any', 'Any'],
].map(([value, label]) => ({ value, label }))

const districts = ref([])
const active = ref('districts')

const tehsilFields = computed(() => [
  {
    key: 'district_id',
    label: 'District',
    type: 'select',
    integer: true,
    required: true,
    options: districts.value.map((district) => ({
      value: district.id,
      label: `${district.name} (${district.code})`,
    })),
  },
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'is_active', label: 'Active', type: 'checkbox' },
])

const tabs = computed(() => [
  {
    id: 'districts',
    label: 'Districts',
    panel: {
      endpoint: '/api/v1/districts',
      searchPlaceholder: 'Name or code',
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'code', label: 'Code' },
        { key: 'is_active', label: 'Active', boolean: true },
      ],
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'code', label: 'Code', type: 'text', required: true },
        { key: 'is_active', label: 'Active', type: 'checkbox' },
      ],
      blank: { name: '', code: '', is_active: true },
    },
  },
  {
    id: 'tehsils',
    label: 'Tehsils',
    panel: {
      endpoint: '/api/v1/tehsils',
      searchPlaceholder: 'Name',
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'district_name', label: 'District' },
        { key: 'is_active', label: 'Active', boolean: true },
      ],
      fields: tehsilFields.value,
      blank: { district_id: '', name: '', is_active: true },
      extraFilters: [
        {
          key: 'district_id',
          label: 'District',
          options: districts.value.map((district) => ({
            value: String(district.id),
            label: district.name,
          })),
        },
      ],
    },
  },
  {
    id: 'provinces',
    label: 'Provinces',
    panel: {
      endpoint: '/api/v1/provinces',
      searchPlaceholder: 'Name',
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'is_active', label: 'Active', boolean: true },
      ],
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'is_active', label: 'Active', type: 'checkbox' },
      ],
      blank: { name: '', is_active: true },
    },
  },
  {
    id: 'qualifications',
    label: 'Qualifications',
    panel: {
      endpoint: '/api/v1/qualifications',
      searchPlaceholder: 'Name',
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'is_agriculture_degree', label: 'Agriculture degree', boolean: true },
        { key: 'is_active', label: 'Active', boolean: true },
      ],
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'is_agriculture_degree', label: 'Agriculture degree', type: 'checkbox' },
        { key: 'is_active', label: 'Active', type: 'checkbox' },
      ],
      blank: { name: '', is_agriculture_degree: false, is_active: true },
    },
  },
  {
    id: 'document-types',
    label: 'Document types',
    panel: {
      endpoint: '/api/v1/document-types',
      searchPlaceholder: 'Name',
      columns: [
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'applies_to', label: 'Applies to' },
        { key: 'has_expiry', label: 'Has expiry', boolean: true },
        { key: 'is_active', label: 'Active', boolean: true },
      ],
      fields: [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'category', label: 'Category', type: 'select', required: true, options: documentCategories },
        { key: 'applies_to', label: 'Applies to', type: 'select', required: true, options: appliesTo },
        { key: 'has_expiry', label: 'Has expiry', type: 'checkbox' },
        { key: 'is_active', label: 'Active', type: 'checkbox' },
      ],
      blank: { name: '', category: 'other', applies_to: 'any', has_expiry: false, is_active: true },
      extraFilters: [
        { key: 'category', label: 'Category', options: documentCategories },
        { key: 'applies_to', label: 'Applies to', options: appliesTo },
      ],
    },
  },
])

const activeTab = computed(() => tabs.value.find((tab) => tab.id === active.value))

async function loadDistricts() {
  const collected = []
  let page = 1
  let lastPage = 1

  do {
    const { response, payload } = await api(`/api/v1/districts?per_page=100&page=${page}&sort=name`)

    if (!response.ok) {
      return
    }

    collected.push(...payload.data)
    lastPage = payload.meta.last_page
    page += 1
  } while (page <= lastPage)

  districts.value = collected
}

onMounted(loadDistricts)
</script>

<template>
  <div class="grid gap-6">
    <PageSection accent="slate" eyebrow="Settings" title="Lookups">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="rounded-md px-3 py-1.5 text-sm ring-1 transition"
          :class="tab.id === active ? 'bg-slate-100 font-medium text-slate-900 ring-slate-300' : 'bg-background text-muted-foreground ring-border hover:bg-muted'"
          @click="active = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>
    </PageSection>
    <CatalogPanel
      v-if="activeTab"
      :key="activeTab.id"
      :endpoint="activeTab.panel.endpoint"
      :search-placeholder="activeTab.panel.searchPlaceholder"
      :columns="activeTab.panel.columns"
      :fields="activeTab.panel.fields"
      :blank="activeTab.panel.blank"
      :extra-filters="activeTab.panel.extraFilters || []"
      eyebrow="Lookups"
      :title="activeTab.label"
    />
  </div>
</template>
