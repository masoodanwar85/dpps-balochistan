<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { portalNavigation, visibleNavigation } from '@/navigation'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const menuOpen = ref(false)
const userOpen = ref(false)
const settingsOpen = ref(false)
const noticesOpen = ref(false)
const notices = ref([])
const unread = ref(0)

const companyPortal = computed(() => auth.user?.user_type === 'company')
const items = computed(() => (
  companyPortal.value
    ? visibleNavigation(auth.permissions, portalNavigation)
    : visibleNavigation(auth.permissions)
))
const title = computed(() => route.meta.title || 'Digital Plant Protection System')

function settingsActive() {
  return route.path.startsWith('/settings')
}

async function logout() {
  userOpen.value = false
  await auth.logout()
  await router.push({ name: 'login' })
}

function closeMenu() {
  menuOpen.value = false
}

async function loadNotices() {
  const { response, payload } = await api('/api/v1/notifications')

  if (!response.ok) {
    return
  }

  notices.value = payload.data || []
  unread.value = payload.meta?.unread || 0
}

async function openNotices() {
  noticesOpen.value = !noticesOpen.value
  userOpen.value = false

  if (noticesOpen.value) {
    await loadNotices()
  }
}

async function markRead(row) {
  await api(`/api/v1/notifications/${row.id}/read`, { method: 'POST', body: {} })
  await loadNotices()
}

onMounted(loadNotices)
</script>

<template>
  <div class="min-h-svh bg-muted md:grid md:grid-cols-[16rem_1fr]">
    <aside
      class="border-b bg-card md:border-r md:border-b-0"
      :class="menuOpen ? 'block' : 'hidden md:block'"
    >
      <div class="border-b px-4 py-4">
        <p class="text-sm font-semibold tracking-wide">{{ companyPortal ? 'COMPANY PORTAL' : 'DIGITAL PLANT PROTECTION SYSTEM' }}</p>
        <p class="mt-1 text-xs text-muted-foreground">Directorate of Plant Protection, Quetta</p>
      </div>
      <nav aria-label="Main" class="grid gap-1 p-3">
        <template v-for="item in items" :key="item.label">
          <div v-if="item.children">
            <button
              type="button"
              class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
              :aria-expanded="settingsOpen || settingsActive()"
              @click="settingsOpen = !settingsOpen"
            >
              Settings
              <span aria-hidden="true">{{ settingsOpen || settingsActive() ? '▾' : '▸' }}</span>
            </button>
            <div v-if="settingsOpen || settingsActive()" class="mt-1 grid gap-1 pl-3">
              <RouterLink
                v-for="child in item.children"
                :key="child.name"
                :to="child.to"
                class="rounded-md px-3 py-2 text-sm hover:bg-accent"
                active-class="bg-accent font-medium"
                @click="closeMenu"
              >
                {{ child.label }}
              </RouterLink>
            </div>
          </div>
          <RouterLink
            v-else
            :to="item.to"
            class="rounded-md px-3 py-2 text-sm hover:bg-accent"
            :exact-active-class="item.to === '/' ? 'bg-accent font-medium' : ''"
            :active-class="item.to === '/' ? '' : 'bg-accent font-medium'"
            :class="item.to !== '/' && route.path.startsWith(item.to) ? 'bg-accent font-medium' : ''"
            @click="closeMenu"
          >
            {{ item.label }}
          </RouterLink>
        </template>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-col">
      <header class="flex items-center gap-3 border-b bg-card px-4 py-3">
        <Button class="md:hidden" variant="outline" size="sm" @click="menuOpen = !menuOpen">
          Menu
        </Button>
        <h1 class="min-w-0 flex-1 text-sm font-semibold tracking-wide md:text-base">
          {{ title }}
        </h1>
        <div class="relative">
          <Button variant="outline" size="sm" aria-label="Notifications" @click="openNotices">
            Notifications<span v-if="unread"> ({{ unread }})</span>
          </Button>
          <div v-if="noticesOpen" class="absolute right-0 z-10 mt-2 w-80 rounded-md border bg-popover p-2 shadow-md">
            <p v-if="notices.length === 0" class="px-2 py-1 text-sm text-muted-foreground">No notifications.</p>
            <button
              v-for="row in notices"
              :key="row.id"
              type="button"
              class="block w-full rounded-md px-2 py-2 text-left text-sm hover:bg-accent"
              @click="markRead(row)"
            >
              <span class="font-medium">{{ row.title }}</span>
              <span class="block text-muted-foreground">{{ row.body }}</span>
            </button>
          </div>
        </div>
        <div class="relative">
          <Button variant="outline" size="sm" @click="userOpen = !userOpen; noticesOpen = false">
            {{ auth.user?.name }}
          </Button>
          <div
            v-if="userOpen"
            class="absolute right-0 z-10 mt-2 w-48 rounded-md border bg-popover p-1 shadow-md"
          >
            <RouterLink
              :to="{ name: 'change-password' }"
              class="block rounded-md px-3 py-2 text-sm hover:bg-accent"
              @click="userOpen = false"
            >
              Change password
            </RouterLink>
            <button
              type="button"
              class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
              @click="logout"
            >
              Log out
            </button>
          </div>
        </div>
      </header>
      <main class="min-h-[50vh] p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
