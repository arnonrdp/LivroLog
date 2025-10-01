<template>
  <q-dialog v-model="showDialog" persistent>
    <q-card class="q-dialog-plugin" style="max-width: 100%; max-height: 90vh; width: 800px">
      <q-card-section class="row items-center q-pb-sm">
        <div>
          <div class="text-h6">{{ book?.title }}</div>
          <div v-if="book?.subtitle" class="text-subtitle2 text-grey-7">{{ book.subtitle }}</div>
        </div>
        <q-space />
        <q-btn
          v-if="shouldShowAmazonButton"
          class="q-mr-sm"
          :color="amazonButtonColor"
          dense
          :disable="book?.asin_status === 'failed'"
          flat
          icon="shopping_cart"
          :loading="book?.asin_status === 'processing'"
          round
        >
          <q-tooltip anchor="center left" self="center right">{{ amazonTooltipText }}</q-tooltip>

          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 200px">
              <q-item v-for="link in amazonLinks" :key="link.region" v-close-popup class="q-py-sm" clickable :href="link.url" target="_blank">
                <q-item-section avatar>
                  <q-icon name="shopping_cart" size="sm" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ link.label }}</q-item-label>
                  <q-item-label caption>{{ link.domain }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="open_in_new" size="xs" />
                </q-item-section>
              </q-item>

              <q-item v-if="!amazonLinks.length" class="text-grey-6">
                <q-item-section>
                  <q-item-label>{{ $t('loading-amazon-links') }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <q-card-section class="row q-col-gutter-md q-py-md">
        <div class="col-3">
          <img
            v-if="book?.thumbnail"
            :alt="`Cover of ${book?.title}`"
            class="full-width"
            :src="book.thumbnail"
            style="max-height: 150px; object-fit: contain; border-radius: 4px"
          />
          <div v-else class="bg-grey-3 full-width text-center q-pa-md" style="height: 150px; border-radius: 4px">
            <q-icon color="grey-5" name="book" size="3em" />
          </div>
        </div>
        <div class="col-9">
          <div class="text-subtitle1 text-grey-8 q-mb-xs">{{ book?.authors }}</div>
          <div v-if="book?.formatted_description || book?.description" class="text-body2 text-grey-7">
            <FormattedDescription
              v-if="book.formatted_description"
              :formatted-description="book.formatted_description"
              :show-full-description="showFullDescription"
            />
            <span v-else>
              <span v-if="!showFullDescription && book.description && book.description.length > 350">
                {{ book.description.substring(0, 350) }}...
              </span>
              <span v-else>{{ book.description }}</span>
            </span>
            <q-btn
              v-if="hasLongDescription"
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

      <q-card-section v-if="book?.isbn || book?.page_count || displayLanguage || book?.categories || book?.publisher">
        <div class="text-subtitle1 q-mb-md row items-center">
          <q-icon class="q-mr-sm" name="info" />
          {{ $t('information') }}
        </div>
        <div class="row q-col-gutter-sm">
          <div v-if="book?.isbn" class="col-6">
            <div class="text-h6 text-grey-6">{{ $t('isbn') }}</div>
            <div class="text-body1">{{ book.isbn }}</div>
          </div>
          <div v-if="book?.page_count" class="col-6">
            <div class="text-h6 text-grey-6">{{ $t('pages') }}</div>
            <div class="text-body1">{{ book.page_count }}</div>
          </div>
          <div v-if="displayLanguage" class="col-6">
            <div class="text-h6 text-grey-6">{{ $t('language') }}</div>
            <div class="text-body1">{{ displayLanguage }}</div>
          </div>
          <div v-if="book?.publisher" class="col-6">
            <div class="text-h6 text-grey-6">{{ $t('publisher') }}</div>
            <div class="text-body1">{{ book.publisher }}</div>
          </div>
        </div>
      </q-card-section>

      <q-separator v-if="book?.isbn || book?.page_count || displayLanguage || book?.categories || book?.publisher" />

      <!-- Reading Details Section -->
      <q-card-section v-if="(!props.userIdentifier && isBookInLibrary) || (props.userIdentifier && book?.pivot)">
        <div class="text-subtitle1 q-mb-md row items-center">
          <q-icon class="q-mr-sm" name="auto_stories" />
          {{ $t('reading-details') }}
        </div>

        <!-- My shelf: Editable inputs -->
        <div v-if="!props.userIdentifier" class="row q-col-gutter-md">
          <div class="col-6">
            <q-select
              v-model="form.reading_status"
              dense
              emit-value
              :label="$t('reading-status')"
              map-options
              :options="readingStatusOptions"
              outlined
              @update:model-value="() => onUpdateBookData({ reading_status: form.reading_status })"
            />
          </div>
          <div class="col-6">
            <q-input
              v-model="form.read_at"
              dense
              :label="$t('read-date')"
              outlined
              type="date"
              @blur="() => onUpdateBookData({ read_at: form.read_at })"
            />
          </div>
        </div>

        <!-- Other user's shelf: Read-only display -->
        <div v-else class="row q-col-gutter-md">
          <div :class="book?.pivot?.read_at ? 'col-6' : 'col-12'">
            <div class="text-h6 text-grey-6">{{ $t('reading-status') }}</div>
            <div class="text-body1">
              {{ book?.pivot?.reading_status ? getReadingStatusLabel(book.pivot.reading_status) : '-' }}
            </div>
          </div>
          <div v-if="book?.pivot?.read_at" class="col-6">
            <div class="text-h6 text-grey-6">{{ $t('read-date') }}</div>
            <div class="text-body1">
              {{ new Date(book.pivot.read_at).toLocaleDateString() }}
            </div>
          </div>
        </div>
      </q-card-section>

      <q-separator v-if="(!props.userIdentifier && isBookInLibrary) || (props.userIdentifier && book?.pivot)" />

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
          :rules="[(val: string) => !!val || $t('content-required')]"
          type="textarea"
        />
        <q-btn v-if="canAddReview" color="primary" :label="$t('save')" :loading="loading" @click="handleSave" />
      </q-card-section>

      <q-separator />
      <q-card-actions class="q-pa-md">
        <div v-if="!props.userIdentifier" class="row items-center">
          <q-checkbox
            v-model="form.is_private"
            :label="$t('private-book')"
            @update:model-value="
              (newValue) => {
                if (!isInitializing && showDialog && isBookInLibrary && !props.userIdentifier) {
                  onUpdateBookData({ is_private: newValue })
                }
              }
            "
          />
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
import FormattedDescription from '@/components/common/FormattedDescription.vue'
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
const libraryLoading = ref(false)
const loading = ref(false)
const MAX_POLLING_TIME = 120000
const POLLING_INTERVAL = 5000
const pollingInterval = ref<number | null>(null)
const pollingStartTime = ref<Date | null>(null)
const reviewToDelete = ref<string | null>(null)
const showDeleteDialog = ref(false)
const showFullDescription = ref(false)
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

const amazonLinks = computed(() => {
  if (!book.value) return []

  // Use amazon_links from API if available (books in database)
  if (book.value.amazon_links && Array.isArray(book.value.amazon_links)) {
    return book.value.amazon_links
  }

  // Fallback: generate links locally for external search results
  if (book.value.amazon_buy_link) {
    return [
      {
        region: book.value.amazon_region || 'BR',
        label: `Amazon ${book.value.amazon_region || 'BR'}`,
        url: book.value.amazon_buy_link,
        domain: book.value.amazon_region === 'US' ? 'amazon.com' : 'amazon.com.br'
      }
    ]
  }

  return []
})

const userReview = computed(() => {
  const reviews = bookReviews.value
  return reviews.find((review) => review.user_id === userStore.me?.id)
})

const shouldShowAmazonButton = computed(() => {
  if (!book.value) return false

  // Show button if book has Amazon data or is being processed
  return !!(book.value.amazon_asin || book.value.amazon_buy_link || book.value.asin_status)
})

const amazonButtonColor = computed(() => {
  if (!book.value) return 'grey-6'

  // Orange if we have Amazon data available
  if (book.value.amazon_asin || book.value.amazon_buy_link) return 'orange'

  // Grey variations for processing states
  if (book.value.asin_status === 'processing') return 'grey-6'
  if (book.value.asin_status === 'pending') return 'grey-5'
  if (book.value.asin_status === 'failed') return 'grey-4'

  return 'grey-6'
})

const amazonTooltipText = computed(() => {
  if (!book.value) return t('buy-on-amazon')
  if (book.value.asin_status === 'processing' || book.value.asin_status === 'pending') {
    return t('searching-amazon-link')
  }
  if (book.value.asin_status === 'failed') {
    return t('amazon-link-not-found')
  }
  if (book.value.asin_status === 'completed') {
    if (book.value.amazon_asin) {
      return t('buy-on-amazon')
    }
    return t('search-on-amazon')
  }
  return t('buy-on-amazon')
})

const canAddReview = computed(() => !userReview.value && isBookInLibrary.value && getBookId() !== null && !props.userIdentifier)

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

const displayLanguage = computed(() => {
  const language = book.value?.language
  if (!language) return null

  const languageKey = `language-${language.toLowerCase().replace('-', '_')}`
  const translated = t(languageKey)

  return translated !== languageKey ? translated : language
})

const hasLongDescription = computed(() => {
  if (!book.value) return false

  if (book.value.formatted_description) {
    // Calculate total length of formatted description
    let totalLength = 0
    for (const block of book.value.formatted_description) {
      if (block.type === 'paragraph' && block.text) {
        totalLength += block.text.length
      } else if (block.type === 'list' && block.items) {
        totalLength += block.items.join(' ').length
      }
    }
    return totalLength > 350
  }

  return book.value.description && book.value.description.length > 350
})

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
      showFullDescription.value = false
      initialPrivacy.value = null
    }
  },
  { immediate: true }
)

watch(showDialog, (newValue) => {
  if (newValue) {
    if (book.value?.asin_status === 'processing' || book.value?.asin_status === 'pending') {
      startPolling()
    }
  } else {
    stopPolling()
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

  const userBooks = userStore.me.books || []
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
        bookStore.$patch({ _book: updatedBook })
        userBookStore.$patch({ _book: updatedBook })

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

function getReadingStatusLabel(status: string) {
  const statusMap: Record<string, string> = {
    want_to_read: t('want-to-read'),
    reading: t('reading'),
    read: t('read'),
    abandoned: t('abandoned'),
    on_hold: t('on-hold'),
    re_reading: t('re-reading')
  }
  return statusMap[status] || status
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

  await userBookStore.patchUserBook(bookId, updates)

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
</script>

