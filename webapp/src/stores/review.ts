import { i18n } from '@/locales'
import type { CreateReviewRequest, Review, UpdateReviewRequest } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useReviewStore = defineStore('review', {
  state: () => ({
    _isLoading: false,
    _reviews: [] as Review[]
  }),

  getters: {
    reviews: (state) => state._reviews
  },

  actions: {
    async getReviews(id: Review['id']) {
      this._isLoading = true
      return await api
        .get(`/reviews/${id}`)
        .then((response) => response.data)
        .catch((error) => Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async getReviewsByBook(bookId: string) {
      this._isLoading = true
      return await api
        .get(`/reviews?book_id=${bookId}`)
        .then((response) => {
          this._reviews = response.data.data || []
          return this._reviews
        })
        .catch(() => {
          this._reviews = []
          Notify.create({ message: i18n.global.t('error-occurred'), type: 'negative' })
        })
        .finally(() => (this._isLoading = false))
    },

    async postReviews(reviewData: CreateReviewRequest) {
      this._isLoading = true
      return await api
        .post('/reviews', reviewData)
        .then((response) => {
          Notify.create({ message: i18n.global.t('saved-successfully'), type: 'positive' })
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async putReviews(id: Review['id'], reviewData: UpdateReviewRequest) {
      this._isLoading = true
      return await api
        .put(`/reviews/${id}`, reviewData)
        .then((response) => {
          Notify.create({ message: i18n.global.t('updated-successfully'), type: 'positive' })
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async deleteReviews(id: Review['id']) {
      this._isLoading = true
      return await api
        .delete(`/reviews/${id}`)
        .then(() => {
          this._reviews = this._reviews.filter((review) => review.id !== id)

          Notify.create({ message: i18n.global.t('deleted-successfully'), type: 'positive' })
          return true
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async toggleReviewVisibility(review: Review) {
      let newVisibility: 'private' | 'friends' | 'public'
      switch (review.visibility_level) {
        case 'public':
          newVisibility = 'friends'
          break
        case 'friends':
          newVisibility = 'private'
          break
        case 'private':
        default:
          newVisibility = 'public'
          break
      }

      return await this.putReviews(review.id, { visibility_level: newVisibility }).then(() => {
        const reviewIndex = this._reviews.findIndex((r) => r.id === review.id)
        if (reviewIndex !== -1 && this._reviews[reviewIndex]) {
          this._reviews[reviewIndex]!.visibility_level = newVisibility
        }
        return newVisibility
      })
    },

    async postReviewsHelpful(reviewId: Review['id']) {
      this._isLoading = true
      return await api
        .post(`/reviews/${reviewId}/helpful`)
        .then(() => {
          Notify.create({ message: i18n.global.t('marked-as-helpful'), type: 'positive' })
          return true
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    clearReviews() {
      this._reviews = []
    }
  }
})
