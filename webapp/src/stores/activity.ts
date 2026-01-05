import { i18n } from '@/locales'
import type { ActivityGroup, FeedMeta, FeedResponse } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

const defaultMeta: FeedMeta = {
  total: 0,
  current_page: 1,
  per_page: 20,
  last_page: 1
}

export const useActivityStore = defineStore('activity', {
  state: () => ({
    _feed: [] as ActivityGroup[],
    _feedMeta: { ...defaultMeta } as FeedMeta,
    _userActivities: {} as Record<string, ActivityGroup[]>,
    _userMeta: {} as Record<string, FeedMeta>,
    _isLoading: false
  }),

  getters: {
    feed: (state) => state._feed,
    isLoading: (state) => state._isLoading,
    meta: (state) => state._feedMeta,
    getUserActivities: (state) => (userId: string) => state._userActivities[userId] || [],
    getUserMeta: (state) => (userId: string) => state._userMeta[userId] || { ...defaultMeta }
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

          this._feedMeta = data.meta
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

          this._userMeta[userId] = data.meta
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
      this._feedMeta = { ...defaultMeta }
    },

    clearUserActivities(userId?: string) {
      if (userId) {
        delete this._userActivities[userId]
        delete this._userMeta[userId]
      } else {
        this._userActivities = {}
        this._userMeta = {}
      }
    }
  }
})
