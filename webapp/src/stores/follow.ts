import { i18n } from '@/locales'
import type { FollowRequest, FollowResponse, FollowStatus, User } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useFollowStore = defineStore('follow', {
  state: () => ({
    _followStatus: {} as Record<string, FollowStatus>,
    _followers: {} as Record<string, User[]>,
    _following: {} as Record<string, User[]>,
    _isLoading: false
  }),

  getters: {
    isFollowedBy: (state) => (userId: string) => state._followStatus[userId]?.is_followed_by || false,
    isFollowing: (state) => (userId: string) => state._followStatus[userId]?.is_following || false,
    isMutualFollow: (state) => (userId: string) => state._followStatus[userId]?.mutual_follow || false
  },

  actions: {
    async postUserFollow(userId: string): Promise<FollowResponse> {
      return await api
        .post(`/users/${userId}/follow`)
        .then((response) => {
          const data: FollowResponse = response.data

          if (data.success) {
            // Check if the follow request is pending or accepted
            const isPending = data.data?.status === 'pending'

            this._followStatus[userId] = {
              is_following: !isPending, // Only set as following if accepted
              is_followed_by: this._followStatus[userId]?.is_followed_by || false,
              mutual_follow: this._followStatus[userId]?.is_followed_by || false
            }

            const message = isPending ? i18n.global.t('follow-request-sent') : i18n.global.t('followed-successfully')

            Notify.create({ message, type: 'positive' })
          }

          return data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('follow-error'), type: 'negative' })
          throw error
        })
    },

    async deleteUserFollow(userId: string): Promise<FollowResponse> {
      return await api
        .delete(`/users/${userId}/follow`)
        .then((response) => {
          const data: FollowResponse = response.data

          if (data.success) {
            this._followStatus[userId] = {
              is_following: false,
              is_followed_by: this._followStatus[userId]?.is_followed_by || false,
              mutual_follow: false
            }

            // Check if it was a pending request that was removed
            const wasPending = data.data?.was_pending
            const message = wasPending ? i18n.global.t('follow-request-removed') : i18n.global.t('unfollowed-successfully')

            Notify.create({ message, type: 'positive' })
          }

          return data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('unfollow-error'), type: 'negative' })
          throw error
        })
    },

    async getUserFollowers(userId: string): Promise<User[] | null> {
      if (this._followers[userId]) {
        return this._followers[userId]
      }

      this._isLoading = true
      return await api
        .get(`/users/${userId}/followers`)
        .then((response) => {
          const followers: User[] = response.data.data || []
          this._followers[userId] = followers
          return followers
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-loading-followers'), type: 'negative' })
          return null
        })
        .finally(() => (this._isLoading = false))
    },

    async getUserFollowing(userId: string): Promise<User[] | null> {
      if (this._following[userId]) {
        return this._following[userId]
      }

      this._isLoading = true
      return await api
        .get(`/users/${userId}/following`)
        .then((response) => {
          const following: User[] = response.data.data || []
          this._following[userId] = following
          return following
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-loading-following'), type: 'negative' })
          return null
        })
        .finally(() => (this._isLoading = false))
    },

    clearFollowStatus(userId?: string) {
      if (userId) {
        delete this._followStatus[userId]
      } else {
        this._followStatus = {}
      }
    },

    clearFollowersData(userId?: string) {
      if (userId) {
        delete this._followers[userId]
        delete this._following[userId]
      } else {
        this._followers = {}
        this._following = {}
      }
    },

    removeFromFollowingList(profileUserId: string, userId: string) {
      if (this._following[profileUserId]) {
        this._following[profileUserId] = this._following[profileUserId].filter((u) => u.id !== userId)
      }
    },

    async getFollowRequests(): Promise<FollowRequest[]> {
      this._isLoading = true

      return await api
        .get('/follow-requests')
        .then((response) => {
          return response.data.data || []
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-loading-requests'), type: 'negative' })
          return []
        })
        .finally(() => (this._isLoading = false))
    },

    async acceptFollowRequest(followId: number): Promise<boolean> {
      return await api
        .post(`/follow-requests/${followId}`)
        .then((response) => {
          const data = response.data

          if (data.success) {
            Notify.create({ message: i18n.global.t('follow-request-accepted'), type: 'positive' })
            return true
          }

          return false
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-accepting-request'), type: 'negative' })
          return false
        })
    },

    async rejectFollowRequest(followId: number): Promise<boolean> {
      return await api
        .delete(`/follow-requests/${followId}`)
        .then((response) => {
          const data = response.data

          if (data.success) {
            Notify.create({ message: i18n.global.t('follow-request-rejected'), type: 'positive' })
            return true
          }

          return false
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-rejecting-request'), type: 'negative' })
          return false
        })
    }
  }
})
