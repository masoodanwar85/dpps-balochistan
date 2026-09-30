<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: null, required: false },
})
</script>

<template>
  <div
    data-slot="card-header"
    :class="cn('grid auto-rows-min items-start gap-1.5 px-6', props.class)"
  >
    <slot />
  </div>
</template>
