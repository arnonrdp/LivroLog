<template>
  <q-btn flat round @click="showNotifications = true">
    <q-icon name="notifications" />
    <q-badge v-if="unreadCount > 0" color="red" floating>
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </q-badge>
  </q-btn>

  <q-dialog v-model="showNotifications" position="right" full-height>
    <q-card style="width: 400px; max-width: 100vw">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">{{ $t('notifications.title') }}</div>
        <q-space />
        <q-btn
          v-if="unreadCount > 0"
          color="primary"
          flat
          :label="$t('notifications.mark-all-read')"
          no-caps
          @click="markAllRead"
        />
        <q-btn v-close-popup flat icon="close" round />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-spinner v-if="isLoading" class="block q-mx-auto" color="primary" size="32px" />

        <q-list v-else-if="notifications.length > 0" separator>
          <NotificationItem
            v-for="notification in notifications"
            :key="notification.id"
            :notification="notification"
            @click="handleNotificationClick"
          />
        </q-list>

        <div v-else class="text-center text-grey q-pa-lg">
          {{ $t('notifications.empty') }}
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { NewNotificationEvent, Notification } from '@/models'
import { useNotificationStore, useUserStore } from '@/stores'
import { disconnectEcho, getEcho, initEcho } from '@/utils/echo'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NotificationItem from './NotificationItem.vue'

const notificationStore = useNotificationStore()
const userStore = useUserStore()
const router = useRouter()

const showNotifications = ref(false)
let pollInterval: ReturnType<typeof setInterval> | null = null
let channelSubscribed = false

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)
const isLoading = computed(() => notificationStore.isLoading)
const userId = computed(() => userStore.me?.id)

// Subscribe to WebSocket channel
function subscribeToNotifications(): void {
  const echo = getEcho() || initEcho()

  if (!echo || !userId.value) {
    // Fallback to polling if WebSocket not available
    startPolling()
    return
  }

  echo
    .private(`notifications.${userId.value}`)
    .listen('.notification.new', (event: NewNotificationEvent) => {
      notificationStore.addNotificationFromWebSocket(event)
    })
    .subscribed(() => {
      channelSubscribed = true
      notificationStore.setWebSocketConnected(true)
      // Stop polling when WebSocket is connected
      stopPolling()
    })
    .error(() => {
      notificationStore.setWebSocketConnected(false)
      // Fallback to polling on error
      startPolling()
    })
}

// Unsubscribe from WebSocket channel
function unsubscribeFromNotifications(): void {
  const echo = getEcho()

  if (echo && userId.value && channelSubscribed) {
    echo.leave(`notifications.${userId.value}`)
    channelSubscribed = false
  }

  notificationStore.setWebSocketConnected(false)
}

// Fallback polling
function startPolling(): void {
  if (pollInterval) return

  pollInterval = setInterval(() => {
    notificationStore.getUnreadCount()
  }, 30000)
}

function stopPolling(): void {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

onMounted(() => {
  notificationStore.getNotifications()
  subscribeToNotifications()
})

onUnmounted(() => {
  stopPolling()
  unsubscribeFromNotifications()
  disconnectEcho()
})

// Re-subscribe when user changes (login/logout)
watch(userId, (newUserId, oldUserId) => {
  if (oldUserId && oldUserId !== newUserId) {
    unsubscribeFromNotifications()
  }

  if (newUserId) {
    subscribeToNotifications()
  }
})

function handleNotificationClick(notification: Notification) {
  if (!notification.is_read) {
    notificationStore.postNotificationRead(notification.id)
  }

  // Navigate based on notification type
  showNotifications.value = false

  switch (notification.type) {
    case 'activity_liked':
    case 'activity_commented':
      router.push('/feed')
      break
    case 'follow_accepted':
      router.push(`/${notification.actor.username}`)
      break
  }
}

function markAllRead() {
  notificationStore.postReadAll()
}
</script>
