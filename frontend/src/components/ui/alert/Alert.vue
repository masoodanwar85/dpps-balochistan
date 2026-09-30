<script setup>
import { cn } from '@/lib/utils'
import { alertVariants } from '.'

const props = defineProps({
  class: { type: null, required: false },
  variant: { type: String, required: false },
})
</script>

<template>
  <div
    data-slot="alert"
    role="alert"
    :class="cn(alertVariants({ variant: props.variant }), props.class)"
  >
    <slot />
  </div>
</template>
