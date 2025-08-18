import { i18n } from '@/locales'
import type { FollowResponse, FollowStatus } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useFollowStore = defineStore('follow', {
  state: () => ({
    _followStatus: {} as Record<string, FollowStatus>,
    _isLoading: false
  }),

  getters: {
    isFollowedBy: (state) => (userId: string) => state._followStatus[userId]?.is_followed_by || false,
    isFollowing: (state) => (userId: string) => state._followStatus[userId]?.is_following || false,
    isMutualFollow: (state) => (userId: string) => state._followStatus[userId]?.mutual_follow || false
  },

  actions: {
    async postUserFollow(userId: string): Promise<FollowResponse> {
      this._isLoading = true
      return await api
        .post(`/users/${userId}/follow`)
        .then((response) => {
          const data: FollowResponse = response.data

          if (data.success) {
            this._followStatus[userId] = {
              is_following: true,
              is_followed_by: this._followStatus[userId]?.is_followed_by || false,
              mutual_follow: this._followStatus[userId]?.is_followed_by || false
            }

            Notify.create({ message: i18n.global.t('followed-successfully'), type: 'positive' })
          }

          return data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('follow-error'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async deleteUserFollow(userId: string): Promise<FollowResponse> {
      this._isLoading = true
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

            Notify.create({ message: i18n.global.t('unfollowed-successfully'), type: 'positive' })
          }

          return data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('unfollow-error'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    clearFollowStatus(userId?: string) {
      if (userId) {
        delete this._followStatus[userId]
      } else {
        this._followStatus = {}
      }
    }
  }
})
