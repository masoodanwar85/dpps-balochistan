<script setup>
import CatalogPanel from '@/components/CatalogPanel.vue'

const categories = [
  ['insecticide', 'Insecticide'],
  ['herbicide', 'Herbicide'],
  ['fungicide', 'Fungicide'],
  ['acaricide', 'Acaricide'],
  ['rodenticide', 'Rodenticide'],
  ['nematicide', 'Nematicide'],
  ['plant_growth_regulator', 'Plant growth regulator'],
  ['other', 'Other'],
].map(([value, label]) => ({ value, label }))
</script>

<template>
  <CatalogPanel
    endpoint="/api/v1/products"
    eyebrow="Catalog"
    title="Products master"
    search-placeholder="Market name, generic name, concentration, or formulation"
    :columns="[
      { key: 'display_name', label: 'Market name' },
      { key: 'concentration', label: 'Concentration' },
      { key: 'formulation', label: 'Formulation' },
      { key: 'category', label: 'Category' },
      { key: 'is_restricted', label: 'Restricted', boolean: true },
      { key: 'is_active', label: 'Active', boolean: true },
    ]"
    :fields="[
      { key: 'generic_name', label: 'Generic name', type: 'text', required: true },
      { key: 'market_name', label: 'Market name', type: 'text', required: true },
      { key: 'concentration', label: 'Concentration', type: 'text', required: true },
      { key: 'formulation', label: 'Formulation', type: 'text', required: true },
      { key: 'category', label: 'Category', type: 'select', required: true, options: categories },
      { key: 'is_restricted', label: 'Restricted', type: 'checkbox' },
      { key: 'is_active', label: 'Active', type: 'checkbox' },
    ]"
    :blank="{
      generic_name: '',
      market_name: '',
      concentration: '',
      formulation: '',
      category: 'insecticide',
      is_restricted: false,
      is_active: true,
    }"
    :extra-filters="[{ key: 'category', label: 'Category', options: categories }]"
  />
</template>
