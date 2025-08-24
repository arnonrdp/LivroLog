<template>
  <q-dialog v-model="showDialog" persistent>
    <q-card class="q-dialog-plugin" style="max-width: 100%; max-height: 90vh; width: 800px">
      <q-card-section class="row items-center q-pb-sm">
        <div class="text-h6">{{ book.title }}</div>
        <q-space />
        <q-btn
          v-if="shouldShowAmazonButton"
          class="q-mr-sm"
          :color="amazonButtonColor"
          dense
          :disable="props.book.asin_status === 'failed'"
          flat
          :href="amazonButtonHref || undefined"
          icon="shopping_cart"
          :loading="book.asin_status === 'processing'"
          round
          :target="amazonButtonHref ? '_blank' : undefined"
        >
          <q-tooltip>{{ amazonTooltipText }}</q-tooltip>
        </q-btn>
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <q-card-section class="row q-col-gutter-md q-py-md">
        <div class="col-3">
          <img
            v-if="book.thumbnail"
            :alt="`Cover of ${book.title}`"
            class="full-width"
            :src="book.thumbnail"
            style="max-height: 150px; object-fit: contain; border-radius: 4px"
          />
          <div v-else class="bg-grey-3 full-width text-center q-pa-md" style="height: 150px; border-radius: 4px">
            <q-icon color="grey-5" name="book" size="3em" />
          </div>
        </div>
        <div class="col-9">
          <div class="text-subtitle1 text-grey-8 q-mb-xs">{{ book.authors }}</div>
          <div v-if="book.description" class="text-body2 text-grey-7">
            <span v-if="!showFullDescription && book.description.length > 350">{{ book.description.substring(0, 350) }}...</span>
            <span v-else>{{ book.description }}</span>
            <q-btn
              v-if="book.description.length > 350"
              class="q-ml-xs"
              color="primary"
              dense
              flat
              :label="showFullDescription ? $t('see-less') : $t('see-more')"
              size="sm"
              @click="showFullDescription = !showFullDescription"
            />
          </div>
        </div>
      </q-card-section>

      <q-separator />

      <q-scroll-area style="height: 400px">
        <q-card-section v-if="isBookInLibrary">
          <div class="text-subtitle1 q-mb-md row items-center">
            <q-icon class="q-mr-sm" name="auto_stories" />
            {{ $t('reading-details') }}
          </div>
          <div class="row q-col-gutter-md">
            <div class="col-6">
              <q-input v-model="readDate" dense :label="$t('read-date')" outlined type="date" @blur="updateReadDate" />
            </div>
            <div class="col-6">
              <q-select
                v-model="readingStatus"
                dense
                emit-value
                :label="$t('reading-status')"
                map-options
                :options="readingStatusOptions"
                outlined
                @update:model-value="updateReadingStatus"
              />
            </div>
          </div>
        </q-card-section>

        <q-separator v-if="isBookInLibrary" />

        <q-card-section>
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

          <div v-for="review in bookReviews" v-else :key="review.id" class="q-mb-md">
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
                      dense
                      flat
                      :icon="getVisibility(review.visibility_level).icon"
                      size="sm"
                      @click="toggleVisibility(review)"
                    >
                      <q-tooltip>{{ getVisibility(review.visibility_level).tooltip }}</q-tooltip>
                    </q-btn>
                    <q-btn class="text-red-6" dense flat icon="delete" size="sm" @click="deleteReview(review.id)">
                      <q-tooltip>{{ $t('delete') }}</q-tooltip>
                    </q-btn>
                  </div>
                </div>
              </q-card-section>
            </q-card>
          </div>
        </q-card-section>

        <q-separator />

        <q-card-section v-if="!isBookInLibrary && !userReview">
          <div class="text-center q-py-md text-grey-6">
            <q-icon class="q-mb-sm" name="info" size="2em" />
            <div class="text-body2">{{ $t('add-to-library-to-review', 'Adicione este livro à sua estante para poder avaliá-lo') }}</div>
          </div>
        </q-card-section>

        <q-card-section v-if="canAddReview">
          <div class="text-subtitle1 q-mb-md row items-center">
            <q-icon class="q-mr-sm" name="add_comment" />
            {{ $t('add-review') }}
          </div>

          <div class="row items-center q-col-gutter-md q-mb-md">
            <div class="col-4">
              <div class="text-body2">{{ $t('rating') }}</div>
              <q-rating v-model="reviewForm.rating" color="amber" icon="star_border" icon-selected="star" :max="5" size="1.5em" />
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

          <q-input v-model="reviewForm.title" class="q-mb-md" dense :label="$t('title') + ' (' + $t('optional') + ')'" :maxlength="200" outlined />

          <q-input
            v-model="reviewForm.content"
            class="q-mb-xs"
            counter
            :label="$t('content')"
            :maxlength="2000"
            outlined
            rows="3"
            :rules="[(val) => !!val || $t('content-required')]"
            type="textarea"
          />
          <q-btn v-if="canAddReview" color="primary" :label="$t('save')" :loading="loading" @click="handleSave" />
        </q-card-section>
      </q-scroll-area>

      <q-separator />
      <q-card-actions class="q-pa-md">
        <div class="row items-center">
          <q-checkbox v-model="isPrivateBook" :label="$t('private-book')" />
          <q-icon class="q-ml-xs cursor-pointer" name="help_outline" size="sm">
            <q-tooltip>{{ $t('private-book-tooltip') }}</q-tooltip>
          </q-icon>
        </div>
        <q-space />
        <q-btn v-close-popup flat :label="$t('close')" />

        <q-btn
          v-if="!isBookInLibrary"
          color="primary"
          :disable="libraryLoading"
          :label="$t('add-to-library')"
          :loading="libraryLoading"
          @click="addToLibrary"
        />
        <q-btn
          v-else
          color="negative"
          :disable="libraryLoading"
          :label="$t('remove-from-library')"
          :loading="libraryLoading"
          outline
          @click="removeFromLibrary"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>

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
        <q-btn color="negative" flat :label="$t('delete')" :loading="loading" @click="confirmDelete" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { getAmazonRegionConfig, getAmazonSearchUrl } from '@/config/amazon'
import type { Book, CreateReviewRequest, ReadingStatus, Review, UpdateReviewRequest } from '@/models'
import { useBookStore, useReviewStore, useUserBookStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  book: Book
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  readDateUpdated: []
}>()

const { t } = useI18n()
const $q = useQuasar()
const bookStore = useBookStore()
const reviewStore = useReviewStore()
const userBookStore = useUserBookStore()
const userStore = useUserStore()

const loading = ref(false)
const libraryLoading = ref(false)
const readDate = ref('')
const readingStatus = ref<ReadingStatus>('read')
const showSpoiler = ref<Record<string, boolean>>({})
const showDeleteDialog = ref(false)
const reviewToDelete = ref<string | null>(null)
const showFullDescription = ref(false)
const isPrivateBook = ref(false)
const bookReviewsFromApi = ref<Review[]>([])
const reviewForm = ref<CreateReviewRequest>({
  book_id: getBookId() || '',
  title: '',
  content: '',
  rating: 5,
  visibility_level: 'public',
  is_spoiler: false
})
const pollingInterval = ref<number | null>(null)
const pollingStartTime = ref<Date | null>(null)
const isBookInLibrary = ref(false)
const MAX_POLLING_TIME = 120000
const POLLING_INTERVAL = 5000

const showDialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const bookReviews = computed(() => {
  if (bookReviewsFromApi.value.length > 0) {
    return bookReviewsFromApi.value
  }
  return props.book.reviews || []
})

const userReview = computed(() => {
  const reviews = bookReviews.value
  return reviews.find((review) => review.user_id === userStore.me?.id)
})

const amazonBuyLink = computed(() => {
  if (props.book.amazon_buy_link) {
    return props.book.amazon_buy_link
  }

  const locale = t('locale') || 'en-US'
  const { domain, tag } = getAmazonRegionConfig(locale)

  if (props.book.amazon_asin) {
    return `https://www.${domain}/dp/${props.book.amazon_asin}?tag=${tag}`
  }

  let searchTerm = ''

  if (props.book.title && props.book.authors) {
    searchTerm = `${props.book.title} ${props.book.authors}`
  } else if (props.book.title) {
    searchTerm = props.book.title
  } else if (props.book.isbn) {
    searchTerm = props.book.isbn
  }

  if (searchTerm) {
    const searchUrl = getAmazonSearchUrl(domain)
    const encodedTerm = encodeURIComponent(searchTerm)
    return `${searchUrl}?k=${encodedTerm}&i=stripbooks&tag=${tag}&ref=nb_sb_noss&linkCode=ur2&camp=1789&creative=9325`
  }

  return null
})

const shouldShowAmazonButton = computed(() => {
  return (
    !!props.book.amazon_asin ||
    props.book.asin_status === 'pending' ||
    props.book.asin_status === 'processing' ||
    props.book.asin_status === 'completed' ||
    props.book.asin_status === 'failed'
  )
})

const amazonButtonColor = computed(() => {
  if (props.book.amazon_asin) return 'orange'
  if (props.book.asin_status === 'processing') return 'grey-6'
  if (props.book.asin_status === 'pending') return 'grey-5'
  if (props.book.asin_status === 'failed') return 'grey-4'
  return 'grey-6'
})

const amazonButtonHref = computed(() => {
  return props.book.asin_status === 'completed' ? amazonBuyLink.value : null
})

const amazonTooltipText = computed(() => {
  if (props.book.asin_status === 'processing' || props.book.asin_status === 'pending') {
    return t('searching-amazon-link')
  }
  if (props.book.asin_status === 'failed') {
    return t('amazon-link-not-found')
  }
  if (props.book.asin_status === 'completed') {
    if (props.book.amazon_asin) {
      return t('buy-on-amazon')
    }
    return t('search-on-amazon')
  }
  return t('buy-on-amazon')
})

const canAddReview = computed(() => !userReview.value && isBookInLibrary.value && getBookId() !== null)

const visibilityOptions = computed(() => [
  { label: t('private'), value: 'private' },
  { label: t('friends'), value: 'friends' },
  { label: t('public'), value: 'public' }
])

const readingStatusOptions = computed(() => [
  { label: t('want-to-read'), value: 'want_to_read' },
  { label: t('reading'), value: 'reading' },
  { label: t('read'), value: 'read' },
  { label: t('abandoned'), value: 'abandoned' },
  { label: t('on-hold'), value: 'on_hold' },
  { label: t('re-reading'), value: 're_reading' }
])

onMounted(() => {
  if (props.book.asin_status === 'processing') {
    startPolling()
  }
})

onUnmounted(() => {
  stopPolling()
})

watch(
  () => props.modelValue,
  async (newValue) => {
    if (newValue) {
      resetReviewForm()
      bookReviewsFromApi.value = []
      loading.value = true

      const pivotReadAt = props.book.pivot?.read_at
      readDate.value = pivotReadAt ? new Date(pivotReadAt).toISOString().split('T')[0] || '' : ''
      readingStatus.value = props.book.pivot?.reading_status || 'read'

      updateLibraryStatus()

      if (isBookInLibrary.value) {
        const userBooks = userStore.me.books || []
        const bookInLibrary = userBooks.find((book) => {
          if (props.book.id && book.id === props.book.id) return true
          if (props.book.google_id && book.google_id === props.book.google_id) return true
          return false
        })
        isPrivateBook.value = Boolean(bookInLibrary?.pivot?.is_private)
      } else {
        isPrivateBook.value = false
      }

      loadBookReviews().then(() => {
        loading.value = false
      })
    } else {
      bookReviewsFromApi.value = []
      resetReviewForm()
      showDeleteDialog.value = false
      reviewToDelete.value = null
      showFullDescription.value = false
    }
  },
  { immediate: true }
)

watch(isPrivateBook, async (newValue, oldValue) => {
  if (isBookInLibrary.value && newValue !== oldValue && oldValue !== undefined) {
    await updateBookPrivacy(newValue)
  }
})

watch(showDialog, (newValue) => {
  if (newValue && props.book.asin_status === 'processing') {
    startPolling()
  } else if (!newValue) {
    stopPolling()
  }
})

function getBookId(): string | null {
  if (props.book.id && props.book.id.startsWith('B-')) {
    return props.book.id
  }

  const userBooks = userStore.me.books || []
  const internalBook = userBooks.find((book) => book.google_id === props.book.google_id)

  if (internalBook && internalBook.id.startsWith('B-')) {
    return internalBook.id
  }

  return null
}

function startPolling() {
  if (props.book.asin_status !== 'processing') return

  pollingStartTime.value = new Date()

  pollingInterval.value = window.setInterval(() => {
    if (pollingStartTime.value && new Date().getTime() - pollingStartTime.value.getTime() > MAX_POLLING_TIME) {
      console.log('Amazon enrichment polling timeout reached')
      stopPolling()
      return
    }

    bookStore
      .getBook(props.book.id)
      .then((updatedBook) => {
        Object.assign(props.book, updatedBook)

        if (updatedBook.asin_status !== 'processing') {
          console.log(`Amazon enrichment completed with status: ${updatedBook.asin_status}`)
          stopPolling()
        }
      })
      .catch((error) => {
        console.error('Error polling for ASIN updates:', error)
        stopPolling()
      })
  }, POLLING_INTERVAL)
}

function stopPolling() {
  if (pollingInterval.value) {
    window.clearInterval(pollingInterval.value)
    pollingInterval.value = null
    pollingStartTime.value = null
  }
}

function updateLibraryStatus() {
  const bookId = props.book?.id
  const googleId = props.book?.google_id

  if (!bookId && !googleId) {
    isBookInLibrary.value = false
    return
  }

  const userBooks = userStore.me.books || []

  const result = userBooks.some((book) => {
    if (bookId && book.id === bookId) return true
    if (googleId && book.google_id === googleId) return true
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

async function updateBookPrivacy(isPrivate: boolean) {
  const bookId = getBookId()
  if (!bookId) {
    console.error('No book ID available for privacy update')
    return
  }

  await userBookStore.patchUserBookPrivacy(bookId, isPrivate)
  updateLibraryStatus()
}

async function updateReadDate() {
  const bookId = getBookId()
  if (!bookId) {
    console.error('No book ID available for read date update')
    return
  }

  await userBookStore.patchUserBookReadDate(bookId, readDate.value)
  emit('readDateUpdated')
}

async function updateReadingStatus() {
  const bookId = getBookId()
  if (!bookId) {
    console.error('No book ID available for reading status update')
    return
  }
  await userBookStore.patchUserBookStatus(bookId, readingStatus.value)
  emit('readDateUpdated')
}

async function loadBookReviews() {
  const bookId = getBookId()
  if (bookId) {
    return await bookStore
      .getBook(bookId)
      .then((bookWithReviews) => (bookReviewsFromApi.value = bookWithReviews.reviews || []))
      .catch(() => (bookReviewsFromApi.value = []))
  } else {
    bookReviewsFromApi.value = []
    return Promise.resolve()
  }
}

async function confirmDelete() {
  if (!reviewToDelete.value) return

  loading.value = true
  showDeleteDialog.value = false

  const reviewId = reviewToDelete.value

  reviewStore
    .deleteReviews(reviewId)
    .then(() => {
      bookReviewsFromApi.value = bookReviewsFromApi.value.filter((review) => review.id !== reviewId)
      resetReviewForm()
    })
    .catch((error) => console.error('Failed to delete review:', error))
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

  const reviewIndex = bookReviewsFromApi.value.findIndex((r) => r.id === review.id)
  if (reviewIndex !== -1 && bookReviewsFromApi.value[reviewIndex]) {
    bookReviewsFromApi.value[reviewIndex]!.visibility_level = newVisibility
  }

  reviewStore.putReviews(review.id, { visibility_level: newVisibility }).catch((error) => {
    console.error('Failed to update review visibility:', error)

    if (reviewIndex !== -1 && bookReviewsFromApi.value[reviewIndex]) {
      bookReviewsFromApi.value[reviewIndex]!.visibility_level = oldVisibility
    }
  })
}

async function addToLibrary() {
  if (libraryLoading.value) {
    return
  }

  libraryLoading.value = true

  await userBookStore
    .postUserBooks(props.book, isPrivateBook.value)
    .then(() => updateLibraryStatus())
    .finally(() => (libraryLoading.value = false))
}

async function removeFromLibrary() {
  if (libraryLoading.value) {
    return
  }

  const bookId = props.book.id
  const googleId = props.book.google_id

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
          console.log('Review data from store:', reviewData)
          bookReviewsFromApi.value = [...bookReviewsFromApi.value, reviewData]
        } else {
          loadBookReviews()
        }
      }
      resetReviewForm()
    })
    .catch(() => $q.notify({ message: t('error-occurred'), type: 'negative' }))
    .finally(() => (loading.value = false))
}
</script>
