<template>
  <BookDetailsPanel
    v-model="showDialog"
    :amazon-link="preferredAmazonLink?.url"
    :book="book || undefined"
    :is-book-in-library="isBookInLibrary"
    :is-own-shelf="!props.userIdentifier"
    :library-loading="libraryLoading"
    :reviews="bookReviews"
    @add="addToLibrary"
    @remove="removeFromLibrary"
    @replace-cover="openReplaceDialog"
    @update-pivot="onUpdateBookData"
  >
    <template #tags>
      <BookTagsSection v-if="book?.id" :book-id="book.id" :is-book-in-library="isBookInLibrary" :user-identifier="props.userIdentifier" />
    </template>

    <template #reviews>
      <div>
        <div class="text-subtitle1 q-mb-md row items-center">
          <q-icon class="q-mr-sm" name="rate_review" />
          {{ $t('existing-reviews') }}
          <span v-if="!loading && bookReviews.length > 0">&nbsp;({{ bookReviews.length }})</span>
          <q-spinner v-if="loading" class="q-ml-sm" size="16px" />
        </div>

        <div v-if="loading" class="text-center q-py-md">
          <q-spinner size="24px" />
          <div class="text-caption q-mt-sm">{{ $t('loading') }}...</div>
        </div>

        <div v-else-if="bookReviews.length === 0" class="text-center q-py-md text-grey-6">
          <q-icon class="q-mb-sm" name="rate_review" size="2em" />
          <div class="text-body2">{{ $t('no-reviews-yet') }}</div>
        </div>

        <div
          v-for="review in bookReviews"
          v-else
          :key="review.id"
          class="q-mb-md"
          :data-testid="review.user_id === userStore.me?.id ? 'user-review' : 'book-review'"
        >
          <q-card bordered flat>
            <q-card-section class="q-py-sm">
              <div class="row items-center q-mb-xs">
                <q-avatar class="q-mr-sm" size="24px">
                  <img v-if="review.user?.avatar" :src="review.user.avatar" />
                  <q-icon v-else name="person" />
                </q-avatar>
                <div class="col">
                  <div class="text-caption">{{ review.user?.display_name }}</div>
                </div>
                <q-rating color="amber" :model-value="review.rating" readonly size="xs" />
              </div>

              <div v-if="review.title" class="text-body2 text-weight-medium q-mb-xs">
                {{ review.title }}
              </div>

              <div class="text-body2 text-grey-8">
                <div v-if="review.is_spoiler && !showSpoiler[review.id] && review.user_id !== userStore.me?.id">
                  <q-icon class="q-mr-xs" name="warning" size="xs" />
                  <em>{{ $t('spoiler-warning') }}</em>
                  <q-btn class="q-ml-xs" dense flat :label="$t('show')" size="xs" @click="showSpoiler[review.id] = true" />
                </div>
                <div v-else>{{ review.content.substring(0, 150) }}{{ review.content.length > 150 ? '...' : '' }}</div>
              </div>

              <div class="row items-center justify-between q-mt-xs">
                <div class="text-caption text-grey-6">
                  {{ formatDate(review.created_at) }}
                </div>
                <div v-if="review.user_id === userStore.me?.id" class="row q-gutter-xs">
                  <q-btn
                    :color="getVisibility(review.visibility_level).color"
                    data-testid="review-visibility-toggle"
                    dense
                    flat
                    :icon="getVisibility(review.visibility_level).icon"
                    size="sm"
                    @click="toggleVisibility(review)"
                  >
                    <q-tooltip>{{ getVisibility(review.visibility_level).tooltip }}</q-tooltip>
                  </q-btn>
                  <span data-testid="review-visibility-status" style="display: none">{{ review.visibility_level }}</span>
                  <q-btn class="text-red-6" data-testid="delete-review-btn" dense flat icon="delete" size="sm" @click="deleteReview(review.id)">
                    <q-tooltip>{{ $t('delete') }}</q-tooltip>
                  </q-btn>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div v-if="!isBookInLibrary && !userReview" class="text-center q-py-md text-grey-6">
        <q-icon class="q-mb-sm" name="info" size="2em" />
        <div class="text-body2">{{ $t('add-to-library-to-review', 'Adicione este livro à sua estante para poder avaliá-lo') }}</div>
      </div>

      <div v-if="canAddReview" class="q-mt-md">
        <div class="text-subtitle1 q-mb-md row items-center">
          <q-icon class="q-mr-sm" name="add_comment" />
          {{ $t('add-review') }}
        </div>

        <div class="row items-center q-col-gutter-md q-mb-md">
          <div class="col-4">
            <div class="text-body2">{{ $t('rating') }}</div>
            <q-rating
              v-model="reviewForm.rating"
              color="amber"
              data-testid="review-rating"
              icon="star_border"
              icon-selected="star"
              :max="5"
              size="1.5em"
            />
          </div>

          <div class="col-4">
            <q-checkbox v-model="reviewForm.is_spoiler" :label="$t('contains-spoilers')" />
          </div>

          <div class="col-4">
            <q-select
              v-model="reviewForm.visibility_level"
              dense
              emit-value
              :label="$t('visibility')"
              map-options
              :options="visibilityOptions"
              outlined
            />
          </div>
        </div>

        <q-input
          v-model="reviewForm.title"
          class="q-mb-md"
          data-testid="review-title-input"
          dense
          :label="$t('title') + ' (' + $t('optional') + ')'"
          :maxlength="200"
          outlined
        />

        <q-input
          v-model="reviewForm.content"
          class="q-mb-xs"
          counter
          data-testid="review-content-input"
          :label="$t('content')"
          :maxlength="2000"
          outlined
          rows="3"
          :rules="[(val: string) => !!val || $t('content-required')]"
          type="textarea"
        />
        <q-btn v-if="canAddReview" color="primary" data-testid="submit-review-btn" :label="$t('save')" :loading="loading" @click="handleSave" />
      </div>
    </template>
  </BookDetailsPanel>

  <q-dialog v-model="showDeleteDialog" persistent>
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ $t('confirmDelete') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        {{ $t('confirmDeleteMessage') }}
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup color="grey-6" flat :label="$t('cancel')" />
        <q-btn color="negative" data-testid="confirm-delete-btn" flat :label="$t('delete')" :loading="loading" @click="confirmDelete" />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <ChangeCoverDialog v-if="book" v-model="showReplaceDialog" :current-book="book" @replaced="onBookReplaced" />
</template>

<script setup lang="ts">
import BookDetailsPanel from '@/components/home/BookDetailsPanel.vue'
import BookTagsSection from '@/components/home/BookTagsSection.vue'
import ChangeCoverDialog from '@/components/home/ChangeCoverDialog.vue'
import type { Book, CreateReviewRequest, ReadingStatus, Review, UpdateReviewRequest } from '@/models'
import { useBookStore, useReviewStore, useUserBookStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  bookId?: string
  bookData?: Book // Direct book data when not in database
  userIdentifier?: string // if provided, will fetch from user's shelf, otherwise from my shelf
}>()

const { t } = useI18n()
const $q = useQuasar()
const bookStore = useBookStore()
const reviewStore = useReviewStore()
const userBookStore = useUserBookStore()
const userStore = useUserStore()

const showDialog = defineModel<boolean>({ default: false })

const form = reactive({
  is_private: false,
  read_at: '',
  reading_status: 'read' as ReadingStatus
})

const allPollingIntervals = new Set<number>()
const initialPrivacy = ref<boolean | null>(null)
const isBookInLibrary = ref(false)
const isInitializing = ref(false)
const isSaving = ref(false)
const libraryLoading = ref(false)
const loading = ref(false)
const MAX_POLLING_TIME = 120000
const POLLING_INTERVAL = 5000
const pollingInterval = ref<number | null>(null)
const pollingStartTime = ref<Date | null>(null)
const reviewToDelete = ref<string | null>(null)
const showDeleteDialog = ref(false)
const showReplaceDialog = ref(false)
const showSpoiler = ref<Record<string, boolean>>({})

const book = computed(() => {
  // First check if we have direct bookData (for books not in database)
  if (props.bookData && !props.bookId) {
    return props.bookData
  }

  // Check userBookStore.book (but ignore if it's an empty object)
  const userBook = userBookStore.book
  if (userBook && Object.keys(userBook).length > 0) {
    return userBook
  }

  // Otherwise use bookStore
  return bookStore.book
})

const reviewForm = ref<CreateReviewRequest>({
  book_id: '',
  content: '',
  is_spoiler: false,
  rating: 5,
  title: '',
  visibility_level: 'public'
})

const bookReviews = computed(() => {
  return book.value?.reviews || []
})

interface AmazonLink {
  region: string
  label: string
  url: string
  domain: string
  isPreferred?: boolean
}

const amazonLinks = computed((): AmazonLink[] => {
  if (!book.value) return []

  const preferredRegion = userStore.me?.preferred_amazon_region || 'US'
  let links: AmazonLink[] = []

  // Priority 1: Use amazon_links from API if available (books in database)
  if (book.value.amazon_links && Array.isArray(book.value.amazon_links) && book.value.amazon_links.length > 0) {
    links = book.value.amazon_links.map((link) => ({
      ...link,
      isPreferred: link.region === preferredRegion
    }))
  } else if (book.value.amazon_buy_link) {
    // Priority 2: Fallback to amazon_buy_link for external search results
    const region = book.value.amazon_region || 'BR'
    links = [
      {
        region,
        label: `Amazon ${region}`,
        url: book.value.amazon_buy_link,
        domain: region === 'US' ? 'amazon.com' : 'amazon.com.br',
        isPreferred: region === preferredRegion
      }
    ]
  }

  // Sort to put preferred store first
  return links.sort((a, b) => {
    if (a.isPreferred && !b.isPreferred) return -1
    if (!a.isPreferred && b.isPreferred) return 1
    return 0
  })
})

const preferredAmazonLink = computed(() => {
  return amazonLinks.value[0] || null
})

const userReview = computed(() => {
  const reviews = bookReviews.value
  return reviews.find((review) => review.user_id === userStore.me?.id)
})

const canAddReview = computed(() => !userReview.value && isBookInLibrary.value && getBookId() !== null && !props.userIdentifier)

const visibilityOptions = computed(() => [
  { label: t('private'), value: 'private' },
  { label: t('friends'), value: 'friends' },
  { label: t('public'), value: 'public' }
])

onMounted(() => {})

onUnmounted(() => {
  stopPolling()
})

watch(
  showDialog,
  async (newValue) => {
    if (newValue) {
      resetReviewForm()
      // Reviews will be loaded via API
      loading.value = true

      // If bookData is provided directly (book not in database), use it
      if (props.bookData && !props.bookId) {
        isInitializing.value = true
        form.read_at = ''
        form.reading_status = 'read'
        initialPrivacy.value = false
        form.is_private = false

        updateLibraryStatus()
        await nextTick()
        isInitializing.value = false
        loading.value = false
      } else {
        // Otherwise, load from API
        const bookData = await loadBookReviews()

        if (bookData) {
          isInitializing.value = true

          const pivotReadAt = bookData.pivot?.read_at
          form.read_at = pivotReadAt ? new Date(pivotReadAt).toISOString().split('T')[0] || '' : ''
          form.reading_status = bookData.pivot?.reading_status || 'read'

          const privacyValue = Boolean(bookData.pivot?.is_private)
          initialPrivacy.value = privacyValue
          form.is_private = privacyValue

          updateLibraryStatus()
          await nextTick()
          isInitializing.value = false
        } else {
          isInitializing.value = true
          form.read_at = ''
          form.reading_status = 'read'
          initialPrivacy.value = false
          form.is_private = false

          await nextTick()
          isInitializing.value = false
        }

        loading.value = false
      }
    } else {
      // Reviews will be loaded via API
      resetReviewForm()
      showDeleteDialog.value = false
      reviewToDelete.value = null
      initialPrivacy.value = null
    }
  },
  { immediate: true }
)

watch(showDialog, async (newValue) => {
  if (newValue) {
    if (book.value?.asin_status === 'processing' || book.value?.asin_status === 'pending') {
      startPolling()
    }
  } else {
    stopPolling()

    // Wait for any pending saves to complete before clearing stores
    if (isSaving.value) {
      await new Promise<void>((resolve) => {
        const checkSaving = setInterval(() => {
          if (!isSaving.value) {
            clearInterval(checkSaving)
            resolve()
          }
        }, 50)
        // Timeout after 3 seconds
        setTimeout(() => {
          clearInterval(checkSaving)
          resolve()
        }, 3000)
      })
    }

    if (!props.bookData) {
      bookStore.$patch({ _book: null })
      userBookStore.$patch({ _book: {} as Book })
    }
  }
})

function getBookId(): string | null {
  if (props.bookId) {
    return props.bookId
  }

  if (!book.value) return null

  // For direct bookData (not in database), use google_id if available
  if (props.bookData && !props.bookId) {
    return book.value.google_id || null
  }

  if (book.value.id && book.value.id.startsWith('B-')) {
    return book.value.id
  }

  const userBooks = userStore.me?.books || []
  const internalBook = userBooks.find((b) => b.google_id === book.value?.google_id)

  if (internalBook && internalBook.id.startsWith('B-')) {
    return internalBook.id
  }

  return null
}

function startPolling() {
  if (pollingInterval.value) {
    stopPolling()
  }

  if (!book.value || (book.value.asin_status !== 'processing' && book.value.asin_status !== 'pending')) {
    return
  }

  pollingStartTime.value = new Date()

  const intervalId = window.setInterval(async () => {
    if (pollingStartTime.value && new Date().getTime() - pollingStartTime.value.getTime() > MAX_POLLING_TIME) {
      stopPolling()
      return
    }

    await bookStore
      .getBook(book.value!.id, { with: ['details'] })
      .then((updatedBook) => {
        // The poll fetches without pivot/reviews; merge over the current book so
        // the Amazon fields refresh without wiping the user's reading data
        const merged = { ...book.value, ...updatedBook }
        bookStore.$patch({ _book: merged })
        userBookStore.$patch({ _book: merged })

        if (updatedBook.asin_status === 'completed' || updatedBook.asin_status === 'failed') {
          stopPolling()
        }
      })
      .catch(() => stopPolling())
  }, POLLING_INTERVAL)

  pollingInterval.value = intervalId
  allPollingIntervals.add(intervalId)
}

function stopPolling() {
  if (pollingInterval.value) {
    window.clearInterval(pollingInterval.value)
    allPollingIntervals.delete(pollingInterval.value)
    pollingInterval.value = null
    pollingStartTime.value = null
  }

  if (allPollingIntervals.size > 0) {
    allPollingIntervals.forEach((intervalId) => {
      window.clearInterval(intervalId)
    })
    allPollingIntervals.clear()
  }
}

function updateLibraryStatus() {
  const bookId = book.value?.id
  const googleId = book.value?.google_id
  const amazonAsin = book.value?.amazon_asin

  if (!bookId && !googleId && !amazonAsin) {
    isBookInLibrary.value = false
    return
  }

  const userBooks = userStore.me?.books || []

  const result = userBooks.some((book) => {
    if (bookId && book.id === bookId) return true
    if (googleId && book.google_id === googleId) return true
    if (amazonAsin && book.amazon_asin === amazonAsin) return true
    return false
  })

  isBookInLibrary.value = result
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString()
}

function resetReviewForm() {
  reviewForm.value = {
    book_id: getBookId() || '',
    title: '',
    content: '',
    rating: 5,
    visibility_level: 'public',
    is_spoiler: false
  }
}

function getVisibility(visibilityLevel: string) {
  const configs = {
    private: { icon: 'lock', color: 'red', tooltip: t('private') },
    friends: { icon: 'group', color: 'orange', tooltip: t('friends') },
    public: { icon: 'public', color: 'green', tooltip: t('public') }
  }

  return configs[visibilityLevel as keyof typeof configs] || configs.public
}

function deleteReview(reviewId: string) {
  reviewToDelete.value = reviewId
  showDeleteDialog.value = true
}

async function onUpdateBookData(updates: { read_at?: string; is_private?: boolean; reading_status?: ReadingStatus }) {
  // Skip if initializing (to avoid updating during initial data load)
  if (isInitializing.value) return

  // Only allow updates on own shelf
  if (props.userIdentifier) {
    console.warn("Cannot update book data on other user's shelf")
    return
  }

  const bookId = getBookId()
  if (!bookId) {
    console.error('No book ID available for update')
    return
  }

  isSaving.value = true
  await userBookStore
    .patchUserBook(bookId, updates)
    .then(() => {
      // Also update the book in userBookStore to keep it in sync
      if (userBookStore.book && userBookStore.book.pivot) {
        const updatedPivot = { ...userBookStore.book.pivot }
        if (updates.read_at !== undefined) updatedPivot.read_at = updates.read_at
        if (updates.is_private !== undefined) updatedPivot.is_private = updates.is_private
        if (updates.reading_status !== undefined) updatedPivot.reading_status = updates.reading_status
        userBookStore.$patch({ _book: { ...userBookStore.book, pivot: updatedPivot } })
      }
    })
    .finally(() => {
      isSaving.value = false
    })

  // Handle specific post-update actions
  if (updates.is_private !== undefined) {
    updateLibraryStatus()
  }
}

async function loadBookReviews() {
  // If bookData is provided directly, return it (book not in database)
  if (props.bookData && !props.bookId) {
    return props.bookData
  }

  const bookId = getBookId()
  if (!bookId) {
    return Promise.resolve(null)
  }

  try {
    // Use unified API with contextual options
    const options: { with: string[]; user_id?: string } = {
      with: ['reviews', 'details']
    }

    if (props.userIdentifier) {
      // Loading from another user's shelf - include pivot data for that user
      options.with.push('pivot')
      options.user_id = props.userIdentifier
    } else {
      // Loading from my shelf - include pivot data for authenticated user
      options.with.push('pivot')
    }

    const bookData = await bookStore.getBook(bookId, options)

    // Update userBookStore as well for consistency
    userBookStore.$patch({ _book: bookData })

    return bookData
  } catch (error) {
    console.error('Error loading book:', error)
    return null
  }
}

async function confirmDelete() {
  if (!reviewToDelete.value) return

  loading.value = true
  showDeleteDialog.value = false

  const reviewId = reviewToDelete.value

  await reviewStore
    .deleteReviews(reviewId)
    .then(() => {
      // Reviews filtered directly in the book object below

      if (book.value?.reviews) {
        book.value.reviews = book.value.reviews.filter((review) => review.id !== reviewId)
      }

      resetReviewForm()
    })
    .finally(() => {
      loading.value = false
      reviewToDelete.value = null
    })
}

async function toggleVisibility(review: Review) {
  let newVisibility: 'private' | 'friends' | 'public'
  const oldVisibility = review.visibility_level

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

  const reviews = book.value?.reviews
  if (reviews) {
    const reviewIndex = reviews.findIndex((r: Review) => r.id === review.id)
    if (reviewIndex !== -1 && reviews[reviewIndex]) {
      reviews[reviewIndex]!.visibility_level = newVisibility
    }

    reviewStore.putReviews(review.id, { visibility_level: newVisibility }).catch((error) => {
      console.error('Failed to update review visibility:', error)

      if (reviewIndex !== -1 && reviews[reviewIndex]) {
        reviews[reviewIndex]!.visibility_level = oldVisibility
      }
    })
  }
}

async function addToLibrary() {
  if (libraryLoading.value || !book.value) {
    return
  }

  libraryLoading.value = true

  await userBookStore
    .postUserBooks(book.value, form.is_private)
    .then(() => updateLibraryStatus())
    .finally(() => (libraryLoading.value = false))
}

async function removeFromLibrary() {
  if (libraryLoading.value || !book.value) {
    return
  }

  const bookId = book.value.id
  const googleId = book.value.google_id

  let bookToRemoveId: string | undefined

  if (bookId) {
    bookToRemoveId = bookId
  } else if (googleId) {
    const userBooks = userStore.me.books || []
    const foundBook = userBooks.find((book) => book.google_id === googleId)
    bookToRemoveId = foundBook?.id
  }

  if (!bookToRemoveId) {
    return
  }

  libraryLoading.value = true

  await userBookStore
    .deleteUserBook(bookToRemoveId)
    .then(() => updateLibraryStatus())
    .finally(() => (libraryLoading.value = false))
}

async function handleSave() {
  loading.value = true

  const promises = []

  if (reviewForm.value.content.trim()) {
    const existingReview = userReview.value

    if (existingReview) {
      const updateData: UpdateReviewRequest = {
        content: reviewForm.value.content,
        rating: reviewForm.value.rating,
        visibility_level: reviewForm.value.visibility_level
      }

      if (reviewForm.value.title) {
        updateData.title = reviewForm.value.title
      }
      if (reviewForm.value.is_spoiler !== undefined) {
        updateData.is_spoiler = reviewForm.value.is_spoiler
      }

      promises.push(reviewStore.putReviews(existingReview.id, updateData))
    } else {
      const bookId = getBookId()
      if (!bookId) {
        loading.value = false
        return
      }

      const createData: CreateReviewRequest = {
        book_id: bookId,
        content: reviewForm.value.content,
        rating: reviewForm.value.rating,
        visibility_level: reviewForm.value.visibility_level
      }

      if (reviewForm.value.title) {
        createData.title = reviewForm.value.title
      }
      if (reviewForm.value.is_spoiler !== undefined) {
        createData.is_spoiler = reviewForm.value.is_spoiler
      }

      promises.push(reviewStore.postReviews(createData))
    }
  }

  Promise.all(promises)
    .then(async (responses) => {
      if (reviewForm.value.content.trim()) {
        const reviewData = responses[0]
        if (reviewData) {
          if (book.value?.reviews) {
            book.value.reviews = [...book.value.reviews, reviewData]
          }
        } else {
          loadBookReviews()
        }
      }
      resetReviewForm()
    })
    .catch(() => $q.notify({ message: t('error-occurred'), type: 'negative' }))
    .finally(() => (loading.value = false))
}

function openReplaceDialog() {
  // Only allow replacement if book is in library and user is viewing their own shelf
  if (!props.userIdentifier && isBookInLibrary.value && book.value) {
    showReplaceDialog.value = true
  }
}

function onBookReplaced(newBook: Book) {
  // Update the book being viewed (this triggers reactive updates throughout the component)
  bookStore.$patch({ _book: newBook })
  userBookStore.$patch({ _book: newBook })

  // Update library status with the new book
  updateLibraryStatus()

  // No need to reload - the new book already comes with reviews and pivot data from the API
}
</script>
