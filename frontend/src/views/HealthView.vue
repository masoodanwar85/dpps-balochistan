<script setup>
import { onMounted } from 'vue'
import { Alert, AlertTitle } from '@/components/ui/alert'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useHealthStore } from '@/stores/health'

const health = useHealthStore()

onMounted(() => {
  health.check()
})
</script>

<template>
  <main class="grid min-h-svh place-items-center bg-muted p-6">
    <Card class="w-full max-w-lg">
      <CardHeader>
        <CardTitle>Digital Plant Protection System</CardTitle>
        <CardDescription>Directorate of Plant Protection, Quetta</CardDescription>
      </CardHeader>
      <CardContent>
        <Alert
          v-if="health.status === 'ok'"
          class="border-green-600/40 bg-green-50 text-green-800"
        >
          <AlertTitle>API connected</AlertTitle>
        </Alert>
        <Alert v-else-if="health.status === 'error'" variant="destructive">
          <AlertTitle>{{ health.message }}</AlertTitle>
        </Alert>
        <p v-else class="text-sm text-muted-foreground">Checking the API…</p>
      </CardContent>
    </Card>
  </main>
</template>
