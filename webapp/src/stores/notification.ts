import { i18n } from '@/locales'
import type { NewNotificationEvent, Notification, NotificationListResponse, NotificationMeta } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

const defaultMeta: NotificationMeta = {
  total: 0,
  current_page: 1,
  per_page: 20,
  last_page: 1,
  unread_count: 0
}

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    _notifications: [] as Notification[],
    _meta: { ...defaultMeta } as NotificationMeta,
    _unreadCount: 0,
    _isLoading: false,
    _isWebSocketConnected: false
  }),

  getters: {
    notifications: (state) => state._notifications,
    meta: (state) => state._meta,
    unreadCount: (state) => state._unreadCount,
    isLoading: (state) => state._isLoading,
    isWebSocketConnected: (state) => state._isWebSocketConnected
  },

  actions: {
    getNotifications(page = 1): Promise<NotificationListResponse | null> {
      this._isLoading = true

      return api
        .get('/notifications', { params: { page, per_page: 20 } })
        .then((response) => {
          const data: NotificationListResponse = response.data

          if (page === 1) {
            this._notifications = data.data
          } else {
            this._notifications = [...this._notifications, ...data.data]
          }

          this._meta = data.meta
          this._unreadCount = data.meta.unread_count
          return data
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('notifications.error-loading'),
            type: 'negative'
          })
          return null
        })
        .finally(() => {
          this._isLoading = false
        })
    },

    getUnreadCount(): Promise<number> {
      return api
        .get('/notifications/unread-count')
        .then((response) => {
          this._unreadCount = response.data.unread_count
          return this._unreadCount
        })
        .catch(() => 0)
    },

    postNotificationRead(notificationId: string): Promise<boolean> {
      return api
        .post(`/notifications/${notificationId}/read`)
        .then((response) => {
          if (response.data.success) {
            const notification = this._notifications.find((n) => n.id === notificationId)
            if (notification) {
              notification.is_read = true
              notification.read_at = new Date().toISOString()
            }
            this._unreadCount = Math.max(0, this._unreadCount - 1)
          }
          return response.data.success
        })
        .catch(() => false)
    },

    postReadAll(): Promise<boolean> {
      return api
        .post('/notifications/read-all')
        .then((response) => {
          if (response.data.success) {
            this._notifications.forEach((n) => {
              n.is_read = true
              n.read_at = new Date().toISOString()
            })
            this._unreadCount = 0
          }
          return response.data.success
        })
        .catch(() => false)
    },

    clearNotifications() {
      this._notifications = []
      this._meta = { ...defaultMeta }
      this._unreadCount = 0
    },

    postReadByActivity(activityId: string): Promise<number> {
      return api
        .post(`/notifications/read-by-activity/${activityId}`)
        .then((response) => {
          if (response.data.success) {
            const markedCount = response.data.marked_count
            this._notifications.forEach((n) => {
              if (n.activity_id === activityId && !n.is_read) {
                n.is_read = true
                n.read_at = new Date().toISOString()
              }
            })
            this._unreadCount = Math.max(0, this._unreadCount - markedCount)
            return markedCount
          }
          return 0
        })
        .catch(() => 0)
    },

    hasUnreadNotificationForActivity(activityId: string): boolean {
      return this._notifications.some((n) => n.activity_id === activityId && !n.is_read)
    },

    // WebSocket-related actions
    addNotificationFromWebSocket(event: NewNotificationEvent): void {
      // Convert event to Notification format
      const notification: Notification = {
        id: event.id,
        type: event.type,
        actor: event.actor,
        data: event.data,
        activity_id: event.activity_id,
        read_at: null,
        is_read: false,
        created_at: event.created_at
      }

      // Add to beginning of list (newest first)
      this._notifications.unshift(notification)
      this._unreadCount += 1
      this._meta.total += 1

      // Show toast notification
      const actorName = notification.actor.display_name || notification.actor.username
      let message = ''
      switch (notification.type) {
        case 'activity_liked':
          message = i18n.global.t('notifications.liked-your-activity', { name: actorName })
          break
        case 'activity_commented':
          message = i18n.global.t('notifications.commented-on-your-activity', { name: actorName })
          break
        case 'follow_accepted':
          message = i18n.global.t('notifications.accepted-your-follow', { name: actorName })
          break
        default:
          message = i18n.global.t('notifications.new-notification')
      }

      Notify.create({
        message,
        type: 'info',
        icon: 'notifications',
        position: 'top-right',
        timeout: 5000
      })
    },

    setWebSocketConnected(connected: boolean): void {
      this._isWebSocketConnected = connected
    }
  }
})
