<template>
  <q-page class="book-page">
    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <q-spinner-dots color="primary" size="3rem" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <q-icon color="negative" name="error" size="4rem" />
      <h3>{{ $t('book.not-found') }}</h3>
      <q-btn color="primary" :label="$t('book.back-to-search')" no-caps outline to="/search" />
    </div>

    <!-- Book Content -->
    <div v-else-if="book" class="book-content">
      <!-- Back Button -->
      <div class="back-nav q-mb-md">
        <q-btn
          color="grey-7"
          dense
          flat
          icon="arrow_back"
          :label="$t('book.back')"
          no-caps
          @click="goBack"
        />
      </div>

      <!-- Book Header -->
      <div class="book-header">
        <!-- Cover -->
        <div class="book-cover">
          <q-img
            :alt="book.title"
            fit="cover"
            :ratio="2/3"
            :src="book.thumbnail || '/no_cover.jpg'"
          >
            <template v-slot:error>
              <q-img fit="cover" :ratio="2/3" src="/no_cover.jpg" />
            </template>
          </q-img>
        </div>

        <!-- Info -->
        <div class="book-info">
          <h1 class="book-title">{{ book.title }}</h1>
          <p v-if="book.subtitle" class="book-subtitle">{{ book.subtitle }}</p>
          <p class="book-author">{{ $t('book.by-author', { author: book.authors || $t('book.unknown-author') }) }}</p>

          <!-- Rating Summary -->
          <div v-if="stats" class="rating-summary">
            <div class="stars">
              <q-icon
                v-for="n in 5"
                :key="n"
                :color="n <= Math.round(stats.average_rating || 0) ? 'amber' : 'grey-4'"
                name="star"
                size="1.25rem"
              />
            </div>
            <span class="rating-value">{{ stats.average_rating?.toFixed(1) || '-' }}</span>
            <span class="rating-count">({{ $t('book.reviews-count', { count: stats.review_count || 0 }) }})</span>
          </div>

          <!-- Action Buttons -->
          <div class="action-buttons">
            <!-- Add to Shelf Button (for authenticated users) -->
            <template v-if="isAuthenticated">
              <q-btn-dropdown
                v-if="!isInLibrary"
                color="primary"
                :label="$t('book.add-to-shelf')"
                no-caps
                rounded
                unelevated
              >
                <q-list>
                  <q-item
                    v-for="status in readingStatuses"
                    :key="status.value"
                    v-close-popup
                    clickable
                    @click="addToLibrary(status.value)"
                  >
                    <q-item-section>
                      <q-item-label>{{ $t(`reading-status.${status.value}`) }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-btn-dropdown>
              <q-btn
                v-else
                color="positive"
                icon="check"
                :label="$t('book.in-library')"
                no-caps
                outline
                rounded
              />
            </template>

            <!-- CTA for guests -->
            <q-btn
              v-else
              color="primary"
              :label="$t('book.add-to-shelf')"
              no-caps
              rounded
              unelevated
              @click="promptLogin"
            />

            <!-- Amazon Button -->
            <q-btn
              v-if="book.amazon_buy_link"
              class="amazon-btn"
              color="orange"
              icon="shopping_cart"
              :label="$t('book.buy-amazon')"
              no-caps
              outline
              rounded
              :href="book.amazon_buy_link"
              target="_blank"
            />
          </div>
        </div>
      </div>

      <!-- Description Section -->
      <section v-if="book.description" class="section description-section">
        <h2>{{ $t('book.description') }}</h2>
        <div :class="['description-content', { expanded: descriptionExpanded }]">
          <p>{{ book.description }}</p>
        </div>
        <q-btn
          v-if="book.description && book.description.length > 300"
          class="q-mt-sm"
          color="primary"
          dense
          flat
          :label="descriptionExpanded ? $t('book.see-less') : $t('book.see-more')"
          no-caps
          @click="descriptionExpanded = !descriptionExpanded"
        />
      </section>

      <!-- Details Section -->
      <section class="section details-section">
        <h2>{{ $t('book.details') }}</h2>
        <div class="details-grid">
          <div v-if="book.isbn" class="detail-item">
            <span class="detail-label">ISBN</span>
            <span class="detail-value">{{ book.isbn }}</span>
          </div>
          <div v-if="book.page_count" class="detail-item">
            <span class="detail-label">{{ $t('book.pages') }}</span>
            <span class="detail-value">{{ book.page_count }}</span>
          </div>
          <div v-if="book.language" class="detail-item">
            <span class="detail-label">{{ $t('book.language') }}</span>
            <span class="detail-value">{{ book.language.toUpperCase() }}</span>
          </div>
          <div v-if="book.publisher" class="detail-item">
            <span class="detail-label">{{ $t('book.publisher') }}</span>
            <span class="detail-value">{{ book.publisher }}</span>
          </div>
        </div>
      </section>

      <!-- Reviews Section -->
      <section class="section reviews-section">
        <div class="section-header">
          <h2>{{ $t('book.reviews') }} ({{ reviews.length }})</h2>
        </div>

        <!-- Loading reviews -->
        <div v-if="isLoadingReviews" class="reviews-loading">
          <q-spinner-dots color="primary" size="2rem" />
        </div>

        <!-- No reviews -->
        <div v-else-if="reviews.length === 0" class="no-reviews">
          <p>{{ $t('book.no-reviews') }}</p>
        </div>

        <!-- Reviews list -->
        <div v-else class="reviews-list">
          <div v-for="review in reviews" :key="review.id" class="review-card">
            <div class="review-header">
              <q-avatar size="40px">
                <q-img v-if="review.user?.avatar" :src="review.user.avatar" />
                <q-icon v-else color="grey" name="person" size="24px" />
              </q-avatar>
              <div class="review-meta">
                <router-link
                  v-if="review.user?.username"
                  class="reviewer-name"
                  :to="`/${review.user.username}`"
                >
                  {{ review.user?.display_name || review.user?.username || $t('book.anonymous') }}
                </router-link>
                <span v-else class="reviewer-name">
                  {{ review.user?.display_name || $t('book.anonymous') }}
                </span>
                <div class="review-rating">
                  <q-icon
                    v-for="n in 5"
                    :key="n"
                    :color="n <= review.rating ? 'amber' : 'grey-4'"
                    name="star"
                    size="0.9rem"
                  />
                  <span v-if="review.created_at" class="review-date">
                    {{ formatDate(review.created_at) }}
                  </span>
                </div>
              </div>
            </div>
            <div class="review-content">
              <p v-if="review.title" class="review-title">{{ review.title }}</p>
              <p class="review-text">{{ review.content }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA for guests -->
      <section v-if="!isAuthenticated" class="cta-section">
        <div class="cta-content">
          <q-icon color="primary" name="bookmark_add" size="2.5rem" />
          <h3>{{ $t('book.cta-title') }}</h3>
          <p>{{ $t('book.cta-subtitle') }}</p>
          <div class="cta-buttons">
            <q-btn
              color="primary"
              :label="$t('signup')"
              no-caps
              rounded
              unelevated
              @click="openRegister"
            />
            <q-btn
              color="primary"
              flat
              :label="$t('book.already-have-account')"
              no-caps
              @click="promptLogin"
            />
          </div>
        </div>
      </section>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import type { Book, ReadingStatus, Review } from '@/models'
import { useAuthStore, useBookStore, useUserBookStore, useUserStore } from '@/stores'
import api from '@/utils/axios'
import { Notify } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

interface BookStats {
  total_readers: number
  average_rating: number | null
  review_count: number
  rating_distribution: Record<string, number>
}

const props = defineProps<{
  bookId: string
}>()

const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const userStore = useUserStore()
const bookStore = useBookStore()
const userBookStore = useUserBookStore()

const book = ref<Book | null>(null)
const stats = ref<BookStats | null>(null)
const reviews = ref<Review[]>([])
const isLoading = ref(true)
const isLoadingReviews = ref(false)
const error = ref(false)
const descriptionExpanded = ref(false)

const isAuthenticated = computed(() => authStore.isAuthenticated)

const isInLibrary = computed(() => {
  if (!isAuthenticated.value || !book.value) return false
  const userBooks = userStore.me?.books || []
  return userBooks.some((ub) => ub.id === book.value?.id)
})

const readingStatuses: { value: ReadingStatus }[] = [
  { value: 'want_to_read' },
  { value: 'reading' },
  { value: 'read' }
]

onMounted(() => {
  loadBook()
})

function loadBook() {
  isLoading.value = true
  error.value = false

  // Load book data
  api
    .get(`/books/${props.bookId}`, {
      params: {
        with: ['details']
      }
    })
    .then((response) => {
      book.value = response.data
      loadStats()
      loadReviews()
    })
    .catch((err) => {
      console.error('Error loading book:', err)
      error.value = true
    })
    .finally(() => {
      isLoading.value = false
    })
}

function loadStats() {
  if (!props.bookId) return

  api
    .get(`/books/${props.bookId}/stats`)
    .then((response) => {
      stats.value = response.data
    })
    .catch((err) => {
      console.error('Error loading stats:', err)
    })
}

function loadReviews() {
  if (!props.bookId) return

  isLoadingReviews.value = true

  api
    .get(`/books/${props.bookId}/reviews`)
    .then((response) => {
      reviews.value = response.data.data || []
    })
    .catch((err) => {
      console.error('Error loading reviews:', err)
    })
    .finally(() => {
      isLoadingReviews.value = false
    })
}

function addToLibrary(readingStatus: ReadingStatus) {
  if (!book.value) return

  userBookStore
    .postUserBooks(book.value, false, readingStatus)
    .then(() => {
      Notify.create({ message: t('book.added-to-library'), type: 'positive' })
      // Refresh user data to update isInLibrary
      authStore.getMe()
    })
    .catch((err) => {
      console.error('Error adding to library:', err)
      Notify.create({ message: t('book.error-adding'), type: 'negative' })
    })
}

function promptLogin() {
  authStore.setRedirectPath(`/books/${props.bookId}`)
  authStore.openAuthModal('login')
}

function openRegister() {
  authStore.setRedirectPath(`/books/${props.bookId}`)
  authStore.openAuthModal('register')
}

function goBack() {
  if (window.history.length > 2) {
    router.back()
  } else {
    router.push('/search')
  }
}

function formatDate(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now.getTime() - date.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays < 1) return t('book.today')
  if (diffDays === 1) return t('book.yesterday')
  if (diffDays < 7) return t('book.days-ago', { days: diffDays })
  if (diffDays < 30) return t('book.weeks-ago', { weeks: Math.floor(diffDays / 7) })
  if (diffDays < 365) return t('book.months-ago', { months: Math.floor(diffDays / 30) })
  return t('book.years-ago', { years: Math.floor(diffDays / 365) })
}
</script>

<style scoped lang="sass">
.book-page
  max-width: 900px
  margin: 0 auto
  padding: 1rem

.loading-container,
.error-container
  align-items: center
  display: flex
  flex-direction: column
  gap: 1rem
  justify-content: center
  min-height: 50vh
  text-align: center

  h3
    color: #4a4a68
    font-size: 1.25rem
    margin: 0

.book-content
  padding-bottom: 2rem

.back-nav
  margin-bottom: 1rem

.book-header
  display: flex
  gap: 2rem
  margin-bottom: 2rem

  @media (max-width: 600px)
    flex-direction: column
    align-items: center
    text-align: center

.book-cover
  border-radius: 8px
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15)
  flex-shrink: 0
  overflow: hidden
  width: 200px

  @media (max-width: 600px)
    width: 160px

.book-info
  flex: 1

.book-title
  color: #1a1a2e
  font-size: 1.75rem
  font-weight: 700
  line-height: 1.2
  margin: 0 0 0.5rem

.book-subtitle
  color: #4a4a68
  font-size: 1.1rem
  margin: 0 0 0.5rem

.book-author
  color: #6b6b8d
  font-size: 1rem
  margin: 0 0 1rem

.rating-summary
  align-items: center
  display: flex
  gap: 0.5rem
  margin-bottom: 1.5rem

  @media (max-width: 600px)
    justify-content: center

.stars
  display: flex

.rating-value
  color: #1a1a2e
  font-size: 1.1rem
  font-weight: 600

.rating-count
  color: #6b6b8d
  font-size: 0.9rem

.action-buttons
  display: flex
  flex-wrap: wrap
  gap: 0.75rem

  @media (max-width: 600px)
    justify-content: center

.amazon-btn
  :deep(.q-btn__content)
    gap: 0.5rem

.section
  margin-bottom: 2rem
  padding-top: 1.5rem
  border-top: 1px solid #e8e8e8

  h2
    color: #1a1a2e
    font-size: 1.25rem
    font-weight: 600
    margin: 0 0 1rem

.description-content
  color: #4a4a68
  line-height: 1.7
  max-height: 150px
  overflow: hidden
  position: relative

  &.expanded
    max-height: none

  &:not(.expanded)::after
    background: linear-gradient(transparent, white)
    bottom: 0
    content: ''
    height: 50px
    left: 0
    position: absolute
    right: 0

  p
    margin: 0

.details-grid
  display: grid
  gap: 1rem
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))

.detail-item
  display: flex
  flex-direction: column
  gap: 0.25rem

.detail-label
  color: #6b6b8d
  font-size: 0.8rem
  text-transform: uppercase

.detail-value
  color: #1a1a2e
  font-size: 0.95rem

.section-header
  align-items: center
  display: flex
  justify-content: space-between
  margin-bottom: 1rem

.reviews-loading
  padding: 2rem
  text-align: center

.no-reviews
  color: #6b6b8d
  padding: 2rem
  text-align: center

  p
    margin: 0

.reviews-list
  display: flex
  flex-direction: column
  gap: 1.5rem

.review-card
  background: #f8f9fc
  border-radius: 12px
  padding: 1.25rem

.review-header
  align-items: flex-start
  display: flex
  gap: 0.75rem
  margin-bottom: 0.75rem

.review-meta
  flex: 1

.reviewer-name
  color: #1a1a2e
  font-weight: 600
  text-decoration: none

  &:hover
    text-decoration: underline

.review-rating
  align-items: center
  display: flex
  gap: 0.5rem
  margin-top: 0.25rem

.review-date
  color: #6b6b8d
  font-size: 0.8rem

.review-content
  .review-title
    color: #1a1a2e
    font-weight: 600
    margin: 0 0 0.5rem

  .review-text
    color: #4a4a68
    line-height: 1.6
    margin: 0

.cta-section
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf3 100%)
  border-radius: 16px
  margin-top: 2rem
  padding: 2.5rem 1.5rem
  text-align: center

.cta-content
  max-width: 400px
  margin: 0 auto

  h3
    color: #1a1a2e
    font-size: 1.25rem
    font-weight: 600
    margin: 1rem 0 0.5rem

  p
    color: #6b6b8d
    font-size: 0.95rem
    margin: 0 0 1.5rem

.cta-buttons
  display: flex
  flex-wrap: wrap
  gap: 0.75rem
  justify-content: center
</style>
