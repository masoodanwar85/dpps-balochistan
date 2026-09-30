<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: null, required: false },
})
</script>

<template>
  <label
    data-slot="label"
    :class="cn('text-sm leading-none font-medium', props.class)"
  >
    <slot />
  </label>
</template>
