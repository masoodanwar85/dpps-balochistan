<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Alert, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const code = ref('')
const submitting = ref(false)

onMounted(() => {
  auth.error = ''
  auth.codeRequired = false
})

async function submit() {
  submitting.value = true

  const ok = await auth.login(email.value, password.value, code.value)

  submitting.value = false

  if (!ok) {
    return
  }

  if (auth.user.must_change_password) {
    await router.push({ name: 'change-password' })

    return
  }

  await router.push(auth.user?.user_type === 'company' ? { name: 'portal' } : { name: 'dashboard' })
}
</script>

<template>
  <main class="grid min-h-svh place-items-center bg-muted p-6">
    <Card class="w-full max-w-md">
      <CardHeader class="text-center">
        <CardTitle>DIGITAL PLANT PROTECTION SYSTEM</CardTitle>
        <CardDescription>Directorate of Plant Protection, Quetta</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="grid gap-4" @submit.prevent="submit">
          <Alert v-if="auth.error" variant="destructive">
            <AlertTitle>{{ auth.error }}</AlertTitle>
          </Alert>

          <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="email"
              type="email"
              autocomplete="username"
              required
            />
          </div>

          <div class="grid gap-2">
            <Label for="password">Password</Label>
            <Input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
            />
          </div>

          <div v-if="auth.codeRequired" class="grid gap-2">
            <Label for="code">Authentication code</Label>
            <Input
              id="code"
              v-model="code"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
            />
          </div>

          <Button type="submit" :disabled="submitting">
            {{ submitting ? 'Signing in…' : 'Login' }}
          </Button>

          <p class="text-sm text-muted-foreground">
            5 failed attempts → locked for 15 minutes
          </p>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
