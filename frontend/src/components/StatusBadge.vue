<script setup>
import { computed } from 'vue'
import { statusTone } from '@/lib/status'

const props = defineProps({
  value: { type: [String, Boolean, Number], required: false, default: '' },
  label: { type: String, required: false, default: '' },
})

const text = computed(() => props.label || String(props.value ?? ''))
const tone = computed(() => statusTone(props.value))
</script>

<template>
  <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="tone">
    {{ text }}
  </span>
</template>
