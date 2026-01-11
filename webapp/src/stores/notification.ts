import { i18n } from '@/locales'
import type { Notification, NotificationListResponse, NotificationMeta } from '@/models'
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
    _isLoading: false
  }),

  getters: {
    notifications: (state) => state._notifications,
    meta: (state) => state._meta,
    unreadCount: (state) => state._unreadCount,
    isLoading: (state) => state._isLoading
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
    }
  }
})
