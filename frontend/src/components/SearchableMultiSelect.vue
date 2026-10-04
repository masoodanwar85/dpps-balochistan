<script setup>
import { computed, ref } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  label: { type: String, default: 'Companies' },
  placeholder: { type: String, default: 'Search…' },
  required: { type: Boolean, default: false },
  id: { type: String, default: 'multi-select' },
})

const emit = defineEmits(['update:modelValue'])

const search = ref('')
const open = ref(false)

const selectedIds = computed(() => props.modelValue.map((id) => Number(id)))

const selectedOptions = computed(() => {
  const selected = new Set(selectedIds.value)

  return props.options.filter((option) => selected.has(Number(option.id)))
})

const filteredOptions = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return props.options
  }

  return props.options.filter((option) => {
    const label = String(option.label || option.name || '')
    const code = String(option.code || option.company_code || '')

    return label.toLowerCase().includes(term) || code.toLowerCase().includes(term)
  })
})

function optionLabel(option) {
  const name = option.label || option.name || ''
  const code = option.code || option.company_code

  return code ? `${name} (${code})` : name
}

function isSelected(id) {
  return selectedIds.value.includes(Number(id))
}

function toggle(id) {
  const value = Number(id)
  const next = isSelected(value)
    ? selectedIds.value.filter((item) => item !== value)
    : [...selectedIds.value, value]

  emit('update:modelValue', next)
}

function remove(id) {
  emit('update:modelValue', selectedIds.value.filter((item) => item !== Number(id)))
}
</script>

<template>
  <div class="dpps-field">
    <Label :for="id">{{ label }}</Label>
    <div class="rounded-md border border-input bg-background shadow-xs">
      <div class="flex flex-wrap gap-1.5 border-b px-2 py-2">
        <span
          v-for="option in selectedOptions"
          :key="option.id"
          class="inline-flex items-center gap-1 rounded bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-900 ring-1 ring-sky-200"
        >
          {{ optionLabel(option) }}
          <button type="button" class="text-sky-700 hover:text-sky-950" :aria-label="`Remove ${optionLabel(option)}`" @click="remove(option.id)">×</button>
        </span>
        <span v-if="selectedOptions.length === 0" class="text-xs text-muted-foreground">
          {{ required ? 'Select at least one' : 'None selected' }}
        </span>
      </div>
      <div class="p-2">
        <Input
          :id="id"
          v-model="search"
          :placeholder="placeholder"
          :aria-label="label"
          autocomplete="off"
          @focus="open = true"
        />
      </div>
      <div v-if="open || search" class="max-h-48 overflow-y-auto border-t">
        <button
          v-for="option in filteredOptions"
          :key="option.id"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-muted/50"
          @click="toggle(option.id)"
        >
          <input type="checkbox" class="pointer-events-none" :checked="isSelected(option.id)" tabindex="-1" readonly>
          <span>{{ optionLabel(option) }}</span>
        </button>
        <p v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-muted-foreground">No matches.</p>
      </div>
    </div>
  </div>
</template>
