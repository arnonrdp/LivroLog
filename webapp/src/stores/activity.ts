import { i18n } from '@/locales'
import type { ActivityGroup, Comment, FeedMeta, FeedResponse } from '@/models'
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
    },

    postActivityLike(activityId: string): Promise<boolean> {
      return api
        .post(`/activities/${activityId}/like`)
        .then((response) => {
          if (response.data.success) {
            this.updateActivityLikeState(activityId, true, response.data.data.likes_count)
          }
          return response.data.success
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-liking'),
            type: 'negative'
          })
          return false
        })
    },

    deleteActivityLike(activityId: string): Promise<boolean> {
      return api
        .delete(`/activities/${activityId}/like`)
        .then((response) => {
          if (response.data.success) {
            this.updateActivityLikeState(activityId, false, response.data.data.likes_count)
          }
          return response.data.success
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-unliking'),
            type: 'negative'
          })
          return false
        })
    },

    updateActivityLikeState(activityId: string, isLiked: boolean, likesCount: number) {
      // Find and update the activity group containing this activity
      const group = this._feed.find((g) => g.first_activity_id === activityId || g.activities.some((a) => a.id === activityId))
      if (group) {
        group.is_liked = isLiked
        group.likes_count = likesCount
      }
    },

    getActivityComments(activityId: string, page = 1): Promise<Comment[]> {
      return api
        .get(`/activities/${activityId}/comments`, { params: { page, per_page: 20 } })
        .then((response) => response.data.data || [])
        .catch(() => [])
    },

    postActivityComment(activityId: string, content: string): Promise<Comment | null> {
      return api
        .post(`/activities/${activityId}/comments`, { content })
        .then((response) => {
          if (response.data.success) {
            const group = this._feed.find((g) => g.first_activity_id === activityId || g.activities.some((a) => a.id === activityId))
            if (group) {
              group.comments_count++
            }
            Notify.create({
              message: i18n.global.t('feed.comment-added'),
              type: 'positive'
            })
            return response.data.data
          }
          return null
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-commenting'),
            type: 'negative'
          })
          return null
        })
    },

    putComment(commentId: string, content: string): Promise<Comment | null> {
      return api
        .put(`/comments/${commentId}`, { content })
        .then((response) => {
          if (response.data.success) {
            return response.data.data
          }
          return null
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-updating-comment'),
            type: 'negative'
          })
          return null
        })
    },

    deleteComment(commentId: string, activityId: string): Promise<boolean> {
      return api
        .delete(`/comments/${commentId}`)
        .then((response) => {
          if (response.data.success) {
            const group = this._feed.find((g) => g.first_activity_id === activityId || g.activities.some((a) => a.id === activityId))
            if (group) {
              group.comments_count = Math.max(0, group.comments_count - 1)
            }
            Notify.create({
              message: i18n.global.t('feed.comment-deleted'),
              type: 'positive'
            })
          }
          return response.data.success
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('feed.error-deleting-comment'),
            type: 'negative'
          })
          return false
        })
    }
  }
})
