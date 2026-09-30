<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: null, required: false },
})
</script>

<template>
  <div
    data-slot="alert-title"
    :class="cn('font-medium', props.class)"
  >
    <slot />
  </div>
</template>
