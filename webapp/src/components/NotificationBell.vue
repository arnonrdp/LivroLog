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
import type { Notification } from '@/models'
import { useNotificationStore } from '@/stores'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotificationItem from './NotificationItem.vue'

const notificationStore = useNotificationStore()
const router = useRouter()

const showNotifications = ref(false)
let pollInterval: ReturnType<typeof setInterval> | null = null

const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)
const isLoading = computed(() => notificationStore.isLoading)

onMounted(() => {
  notificationStore.getNotifications()
  // Poll for unread count every 30 seconds
  pollInterval = setInterval(() => {
    notificationStore.getUnreadCount()
  }, 30000)
})

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval)
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
