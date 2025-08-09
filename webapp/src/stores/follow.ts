import { i18n } from '@/locales'
import type { FollowersResponse, FollowingResponse, FollowResponse, FollowStatus, User } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useFollowStore = defineStore('follow', {
  state: () => ({
    _isLoading: false,
    _followers: [] as Pick<User, 'id' | 'display_name' | 'username' | 'avatar'>[],
    _following: [] as Pick<User, 'id' | 'display_name' | 'username' | 'avatar'>[],
    _followStatus: {} as Record<string, FollowStatus>,
    _followersPagination: {
      current_page: 1,
      per_page: 20,
      total: 0,
      last_page: 1,
      has_more: false
    },
    _followingPagination: {
      current_page: 1,
      per_page: 20,
      total: 0,
      last_page: 1,
      has_more: false
    }
  }),

  getters: {
    isLoading: (state) => state._isLoading,
    followers: (state) => state._followers,
    following: (state) => state._following,
    followersPagination: (state) => state._followersPagination,
    followingPagination: (state) => state._followingPagination,

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

    async getFollowers(userId: string, page: number = 1, perPage: number = 20): Promise<FollowersResponse> {
      this._isLoading = true
      try {
        const response = await api.get(`/users/${userId}/followers`, {
          params: { page, per_page: perPage }
        })
        const data: FollowersResponse = response.data

        if (data.success) {
          if (page === 1) {
            this._followers = data.data.followers
          } else {
            this._followers.push(...data.data.followers)
          }
          this._followersPagination = data.data.pagination
        }

        return data
      } catch (error: any) {
        Notify.create({
          message: error.response?.data?.message || i18n.global.t('followers-error', 'Erro ao carregar seguidores'),
          type: 'negative'
        })
        throw error
      } finally {
        this._isLoading = false
      }
    },

    async getFollowing(userId: string, page: number = 1, perPage: number = 20): Promise<FollowingResponse> {
      this._isLoading = true
      try {
        const response = await api.get(`/users/${userId}/following`, {
          params: { page, per_page: perPage }
        })
        const data: FollowingResponse = response.data

        if (data.success) {
          if (page === 1) {
            this._following = data.data.following
          } else {
            this._following.push(...data.data.following)
          }
          this._followingPagination = data.data.pagination
        }

        return data
      } catch (error: any) {
        Notify.create({
          message: error.response?.data?.message || i18n.global.t('following-error', 'Erro ao carregar seguindo'),
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

    clearFollowers() {
      this._followers = []
      this._followersPagination = {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
        has_more: false
      }
    },

    clearFollowing() {
      this._following = []
      this._followingPagination = {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
        has_more: false
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
