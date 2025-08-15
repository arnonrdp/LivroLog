import { i18n } from '@/locales'
import type { FollowResponse, FollowStatus } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useFollowStore = defineStore('follow', {
  state: () => ({
    _isLoading: false,
    _followStatus: {} as Record<string, FollowStatus>
  }),

  getters: {
    isLoading: (state) => state._isLoading,

    isFollowing: (state) => (userId: string) => {
      return state._followStatus[userId]?.is_following || false
    },

    isFollowedBy: (state) => (userId: string) => {
      return state._followStatus[userId]?.is_followed_by || false
    },

    isMutualFollow: (state) => (userId: string) => {
      return state._followStatus[userId]?.mutual_follow || false
    }
  },

  actions: {
    async followUser(userId: string): Promise<FollowResponse> {
      this._isLoading = true
      try {
        const response = await api.post(`/users/${userId}/follow`)
        const data: FollowResponse = response.data

        if (data.success) {
          // Update follow status
          this._followStatus[userId] = {
            is_following: true,
            is_followed_by: this._followStatus[userId]?.is_followed_by || false,
            mutual_follow: this._followStatus[userId]?.is_followed_by || false
          }

          Notify.create({
            message: i18n.global.t('followed-successfully', 'Usuário seguido com sucesso!'),
            type: 'positive'
          })
        }

        return data
      } catch (error: any) {
        const errorMessage = error.response?.data?.message || i18n.global.t('follow-error', 'Erro ao seguir usuário')
        Notify.create({
          message: errorMessage,
          type: 'negative'
        })
        throw error
      } finally {
        this._isLoading = false
      }
    },

    async unfollowUser(userId: string): Promise<FollowResponse> {
      this._isLoading = true
      try {
        const response = await api.delete(`/users/${userId}/unfollow`)
        const data: FollowResponse = response.data

        if (data.success) {
          // Update follow status
          this._followStatus[userId] = {
            is_following: false,
            is_followed_by: this._followStatus[userId]?.is_followed_by || false,
            mutual_follow: false
          }

          Notify.create({
            message: i18n.global.t('unfollowed-successfully', 'Parou de seguir o usuário'),
            type: 'positive'
          })
        }

        return data
      } catch (error: any) {
        const errorMessage = error.response?.data?.message || i18n.global.t('unfollow-error', 'Erro ao parar de seguir usuário')
        Notify.create({
          message: errorMessage,
          type: 'negative'
        })
        throw error
      } finally {
        this._isLoading = false
      }
    },


    async getFollowStatus(userId: string): Promise<FollowStatus> {
      try {
        const response = await api.get(`/users/${userId}/follow-status`)
        const status: FollowStatus = response.data

        this._followStatus[userId] = status
        return status
      } catch (error: any) {
        // Silent fail para status - não exibir erro
        const defaultStatus: FollowStatus = {
          is_following: false,
          is_followed_by: false,
          mutual_follow: false
        }
        this._followStatus[userId] = defaultStatus
        return defaultStatus
      }
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
