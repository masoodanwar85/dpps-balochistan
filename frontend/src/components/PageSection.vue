<script setup>
import { computed } from 'vue'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { cn } from '@/lib/utils'

const props = defineProps({
  accent: { type: String, required: false, default: 'slate' },
  eyebrow: { type: String, required: false, default: '' },
  title: { type: String, required: false, default: '' },
  contentClass: { type: null, required: false, default: '' },
})

const accents = {
  emerald: { bar: 'bg-emerald-500', text: 'text-emerald-700' },
  sky: { bar: 'bg-sky-500', text: 'text-sky-700' },
  violet: { bar: 'bg-violet-500', text: 'text-violet-700' },
  amber: { bar: 'bg-amber-500', text: 'text-amber-700' },
  orange: { bar: 'bg-orange-500', text: 'text-orange-700' },
  rose: { bar: 'bg-rose-500', text: 'text-rose-700' },
  red: { bar: 'bg-red-500', text: 'text-red-700' },
  slate: { bar: 'bg-slate-400', text: 'text-slate-500' },
}

const tone = computed(() => accents[props.accent] || accents.slate)
</script>

<template>
  <Card class="gap-0 overflow-hidden py-0 shadow-sm">
    <div class="h-1.5" :class="tone.bar" />
    <CardHeader
      v-if="title || eyebrow || $slots.actions || $slots.header"
      class="flex flex-col gap-3 px-5 pt-5 pb-0 sm:flex-row sm:items-start sm:justify-between"
    >
      <div class="min-w-0">
        <p v-if="eyebrow" class="text-xs font-semibold tracking-widest uppercase" :class="tone.text">{{ eyebrow }}</p>
        <h2 v-if="title" class="text-lg font-semibold">{{ title }}</h2>
        <slot name="header" />
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </CardHeader>
    <CardContent :class="cn('px-5 pt-4 pb-5', contentClass)">
      <slot />
    </CardContent>
  </Card>
</template>
