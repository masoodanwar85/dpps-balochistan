<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: null, required: false },
})

const model = defineModel({ type: String, default: '' })
</script>

<template>
  <input
    v-model="model"
    data-slot="input"
    :class="cn(
      'border-input bg-transparent placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      props.class,
    )"
  />
</template>
