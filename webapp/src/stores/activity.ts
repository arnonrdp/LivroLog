import { i18n } from '@/locales'
import type { ActivityGroup, FeedResponse } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useActivityStore = defineStore('activity', {
  state: () => ({
    _feed: [] as ActivityGroup[],
    _userActivities: {} as Record<string, ActivityGroup[]>,
    _isLoading: false,
    _meta: {
      total: 0,
      current_page: 1,
      per_page: 20,
      last_page: 1
    }
  }),

  getters: {
    feed: (state) => state._feed,
    isLoading: (state) => state._isLoading,
    meta: (state) => state._meta,
    getUserActivities: (state) => (userId: string) => state._userActivities[userId] || []
  },

  actions: {
    getFeeds(page = 1): Promise<FeedResponse | null> {
      this._isLoading = true

      return api
        .get('/feeds', { params: { page, per_page: 20 } })
        .then((response) => {
          const data: FeedResponse = response.data

          if (page === 1) {
            this._feed = data.grouped
          } else {
            this._feed = [...this._feed, ...data.grouped]
          }

          this._meta = data.meta
          return data
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-loading'),
            type: 'negative'
          })
          return null
        })
        .finally(() => {
          this._isLoading = false
        })
    },

    fetchUserActivities(userId: string, page = 1): Promise<FeedResponse | null> {
      this._isLoading = true

      return api
        .get(`/users/${userId}/activities`, { params: { page, per_page: 20 } })
        .then((response) => {
          const data: FeedResponse = response.data

          if (page === 1) {
            this._userActivities[userId] = data.grouped
          } else {
            this._userActivities[userId] = [...(this._userActivities[userId] || []), ...data.grouped]
          }

          this._meta = data.meta
          return data
        })
        .catch((error) => {
          if (error.response?.status === 403) {
            return null
          }
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-loading'),
            type: 'negative'
          })
          return null
        })
        .finally(() => {
          this._isLoading = false
        })
    },

    clearFeed() {
      this._feed = []
      this._meta = {
        total: 0,
        current_page: 1,
        per_page: 20,
        last_page: 1
      }
    },

    clearUserActivities(userId?: string) {
      if (userId) {
        delete this._userActivities[userId]
      } else {
        this._userActivities = {}
      }
    }
  }
})
