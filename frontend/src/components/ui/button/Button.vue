<script setup>
import { cn } from '@/lib/utils'
import { buttonVariants } from '.'

const props = defineProps({
  class: { type: null, required: false },
  variant: { type: String, required: false },
  size: { type: String, required: false },
  type: { type: String, required: false, default: 'button' },
})
</script>

<template>
  <button
    data-slot="button"
    :type="props.type"
    :class="cn(buttonVariants({ variant: props.variant, size: props.size }), props.class)"
  >
    <slot />
  </button>
</template>
