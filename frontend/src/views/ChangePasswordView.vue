<script setup>
import { ref } from 'vue'
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
import { firstError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const fieldError = ref('')
const submitting = ref(false)

async function submit() {
  fieldError.value = ''
  submitting.value = true

  const result = await auth.changePassword(
    currentPassword.value,
    password.value,
    passwordConfirmation.value,
  )

  submitting.value = false

  if (!result.ok) {
    fieldError.value = firstError(result.errors) || 'Could not change the password.'

    return
  }

  await router.push(auth.user?.user_type === 'company' ? { name: 'portal' } : { name: 'dashboard' })
}
</script>

<template>
  <main class="grid min-h-svh place-items-center bg-muted p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>Change Password</CardTitle>
        <CardDescription v-if="auth.user?.must_change_password">
          You must change your password before continuing.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form class="grid gap-4" @submit.prevent="submit">
          <Alert v-if="fieldError" variant="destructive">
            <AlertTitle>{{ fieldError }}</AlertTitle>
          </Alert>

          <div class="grid gap-2">
            <Label for="current-password">Current password</Label>
            <Input
              id="current-password"
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
            />
          </div>

          <div class="grid gap-2">
            <Label for="new-password">New password</Label>
            <Input
              id="new-password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>

          <div class="grid gap-2">
            <Label for="confirm-password">Confirm new password</Label>
            <Input
              id="confirm-password"
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>

          <p class="text-sm text-muted-foreground">
            At least 8 characters, including letters and numbers.
          </p>

          <Button type="submit" :disabled="submitting">
            {{ submitting ? 'Saving…' : 'Save' }}
          </Button>

          <RouterLink
            v-if="auth.user && !auth.user.must_change_password"
            :to="auth.user?.user_type === 'company' ? { name: 'portal' } : { name: 'dashboard' }"
            class="text-center text-sm underline"
          >
            Back to dashboard
          </RouterLink>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
