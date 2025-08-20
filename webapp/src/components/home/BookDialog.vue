<template>
  <q-dialog v-model="showDialog" persistent>
    <q-card class="q-dialog-plugin" style="max-width: 100%; max-height: 90vh; width: 800px">
      <!-- Header -->
      <q-card-section class="row items-center q-pb-sm">
        <div class="text-h6">{{ book.title }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <!-- Book Info -->
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

      <!-- Scrollable Content -->
      <q-scroll-area style="height: 400px">
        <!-- Read Date Section (only for books in user's library) -->
        <q-card-section v-if="isBookInLibrary">
          <div class="text-subtitle1 q-mb-md row items-center">
            <q-icon class="q-mr-sm" name="event" />
            {{ $t('read-date') }}
          </div>
          <q-input v-model="readDate" class="q-mb-md" dense :label="$t('read-date')" outlined type="date" />
        </q-card-section>

        <q-separator v-if="isBookInLibrary" />

        <!-- Existing Reviews -->
        <q-card-section>
          <div class="text-subtitle1 q-mb-md row items-center">
            <q-icon class="q-mr-sm" name="rate_review" />
            {{ $t('existing-reviews') }}
            <span v-if="!loading && bookReviews.length > 0">&nbsp;({{ bookReviews.length }})</span>
            <q-spinner v-if="loading" class="q-ml-sm" size="16px" />
          </div>

          <!-- Loading state -->
          <div v-if="loading" class="text-center q-py-md">
            <q-spinner size="24px" />
            <div class="text-caption q-mt-sm">{{ $t('loading') }}...</div>
          </div>

          <!-- No reviews state -->
          <div v-else-if="bookReviews.length === 0" class="text-center q-py-md text-grey-6">
            <q-icon class="q-mb-sm" name="rate_review" size="2em" />
            <div class="text-body2">{{ $t('no-reviews-yet') }}</div>
          </div>

          <!-- Reviews list -->
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
                  <div v-if="review.is_spoiler && !showSpoiler[review.id] && review.user_id !== currentUserId">
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
                  <div v-if="review.user_id === currentUserId" class="row q-gutter-xs">
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

        <!-- Book Not in Library Message -->
        <q-card-section v-if="!isBookInLibrary && !userReview">
          <div class="text-center q-py-md text-grey-6">
            <q-icon class="q-mb-sm" name="info" size="2em" />
            <div class="text-body2">{{ $t('add-to-library-to-review', 'Adicione este livro à sua estante para poder avaliá-lo') }}</div>
          </div>
        </q-card-section>

        <!-- Add Review Section (only for books in user's library) -->
        <q-card-section v-if="canAddReview">
          <div class="text-subtitle1 q-mb-md row items-center">
            <q-icon class="q-mr-sm" name="add_comment" />
            {{ $t('add-review') }}
          </div>

          <!-- Rating -->
          <div class="q-mb-md">
            <div class="text-body2 q-mb-sm">{{ $t('rating') }}</div>
            <q-rating v-model="reviewForm.rating" color="amber" icon="star_border" icon-selected="star" :max="5" size="1.5em" />
          </div>

          <!-- Title -->
          <q-input v-model="reviewForm.title" class="q-mb-md" dense :label="$t('title') + ' (' + $t('optional') + ')'" :maxlength="200" outlined />

          <!-- Content -->
          <q-input
            v-model="reviewForm.content"
            class="q-mb-md"
            :label="$t('content')"
            :maxlength="2000"
            outlined
            rows="3"
            :rules="[(val) => !!val || $t('content-required')]"
            type="textarea"
          />

          <!-- Options Row -->
          <div class="row q-col-gutter-md q-mb-md">
            <!-- Visibility -->
            <div class="col-6">
              <div class="text-body2 q-mb-sm">{{ $t('visibility') }}</div>
              <q-select v-model="reviewForm.visibility_level" dense emit-value map-options :options="visibilityOptions" outlined />
            </div>

            <!-- Spoiler -->
            <div class="col-6 flex items-end">
              <q-checkbox v-model="reviewForm.is_spoiler" :label="$t('contains-spoilers')" />
            </div>
          </div>
        </q-card-section>
      </q-scroll-area>

      <!-- Footer Actions -->
      <q-separator />
      <q-card-actions align="right" class="q-pa-md">
        <q-btn v-close-popup flat :label="$t('close')" />

        <!-- Add/Remove from Library Button -->
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

        <q-btn v-if="canAddReview" color="primary" :label="$t('save')" :loading="loading" @click="handleSave" />
      </q-card-actions>
    </q-card>
  </q-dialog>

  <!-- Delete Confirmation Dialog -->
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
import type { Book, CreateReviewRequest, Review, UpdateReviewRequest } from '@/models'
import { useReviewStore, useUserBookStore, useUserStore } from '@/stores'
import { useQuasar } from 'quasar'
import { computed, ref, watch } from 'vue'
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

const reviewStore = useReviewStore()
const userBookStore = useUserBookStore()
const userStore = useUserStore()

const loading = ref(false)
const libraryLoading = ref(false)
const readDate = ref('')
const showSpoiler = ref<Record<string, boolean>>({})
const editingReview = ref<Review | null>(null)
const showDeleteDialog = ref(false)
const reviewToDelete = ref<string | null>(null)
const showFullDescription = ref(false)
const reviewForm = ref<CreateReviewRequest>({
  book_id: props.book.id,
  title: '',
  content: '',
  rating: 5,
  visibility_level: 'public',
  is_spoiler: false
})

const showDialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})
const bookReviews = computed(() => reviewStore.reviews)
const currentUserId = computed(() => userStore.me?.id)
const userReview = computed(() => bookReviews.value.find((review) => review.user_id === currentUserId.value))
// Use ref instead of computed to avoid reactivity timing issues
const isBookInLibrary = ref(false)

// Function to update library status
function updateLibraryStatus() {
  const bookId = props.book?.id
  const googleId = props.book?.google_id

  if (!bookId && !googleId) {
    isBookInLibrary.value = false
    return
  }

  const userBooks = userStore.me.books || []

  // Check by id first (if available), then by google_id
  const result = userBooks.some((book) => {
    if (bookId && book.id === bookId) return true
    if (googleId && book.google_id === googleId) return true
    return false
  })

  isBookInLibrary.value = result
}
const canAddReview = computed(() => !userReview.value && isBookInLibrary.value)

const visibilityOptions = computed(() => [
  { label: t('private'), value: 'private' },
  { label: t('friends'), value: 'friends' },
  { label: t('public'), value: 'public' }
])

watch(
  () => props.modelValue,
  async (newValue) => {
    if (newValue) {
      // No need for complex store tracking - using userStore.me.books directly

      resetReviewForm()
      reviewStore.clearReviews()
      loading.value = true

      const pivotReadAt = props.book.pivot?.read_at
      readDate.value = pivotReadAt ? new Date(pivotReadAt).toISOString().split('T')[0] || '' : ''

      // Update library status using existing userStore.me.books data
      updateLibraryStatus()

      await loadBookReviews()
      loading.value = false
    } else {
      // Clean up on close
      reviewStore.clearReviews()
      resetReviewForm()
      showDeleteDialog.value = false
      reviewToDelete.value = null
      showFullDescription.value = false
    }
  },
  { immediate: true }
)

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString()
}

function resetReviewForm() {
  reviewForm.value = {
    book_id: props.book.id,
    title: '',
    content: '',
    rating: 5,
    visibility_level: 'public',
    is_spoiler: false
  }
  editingReview.value = null
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

async function loadBookReviews() {
  await reviewStore.getReviewsByBook(props.book.id)
}

async function confirmDelete() {
  if (!reviewToDelete.value) return

  loading.value = true
  showDeleteDialog.value = false

  await reviewStore
    .deleteReviews(reviewToDelete.value)
    .then(() => resetReviewForm())
    .finally(() => {
      loading.value = false
      reviewToDelete.value = null
    })
}

async function toggleVisibility(review: Review) {
  await reviewStore.toggleReviewVisibility(review)
}

async function addToLibrary() {
  // Prevent multiple simultaneous calls
  if (libraryLoading.value) {
    return
  }

  libraryLoading.value = true

  await userBookStore
    .postUserBooks(props.book)
    .then(() => updateLibraryStatus())
    .finally(() => (libraryLoading.value = false))
}

async function removeFromLibrary() {
  // Prevent multiple simultaneous calls
  if (libraryLoading.value) {
    return
  }

  // Find the book ID in user's library (needed for books from search that only have google_id)
  const bookId = props.book.id
  const googleId = props.book.google_id

  let bookToRemoveId: string | undefined

  if (bookId) {
    bookToRemoveId = bookId
  } else if (googleId) {
    // Find the book in user's library by google_id to get its system ID
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

  // Save review if content is provided
  if (reviewForm.value.content.trim()) {
    const existingReview = userReview.value

    if (existingReview) {
      // For update, only include fields that have values
      const updateData: UpdateReviewRequest = {
        content: reviewForm.value.content,
        rating: reviewForm.value.rating,
        visibility_level: reviewForm.value.visibility_level
      }

      // Only add optional fields if they have values
      if (reviewForm.value.title) {
        updateData.title = reviewForm.value.title
      }
      if (reviewForm.value.is_spoiler !== undefined) {
        updateData.is_spoiler = reviewForm.value.is_spoiler
      }

      promises.push(reviewStore.putReviews(existingReview.id, updateData))
    } else {
      // For create, build the request properly
      const createData: CreateReviewRequest = {
        book_id: props.book.id,
        content: reviewForm.value.content,
        rating: reviewForm.value.rating,
        visibility_level: reviewForm.value.visibility_level
      }

      // Only add optional fields if they have values
      if (reviewForm.value.title) {
        createData.title = reviewForm.value.title
      }
      if (reviewForm.value.is_spoiler !== undefined) {
        createData.is_spoiler = reviewForm.value.is_spoiler
      }

      promises.push(reviewStore.postReviews(createData))
    }
  }

  // Save read date separately using the user books endpoint
  if (readDate.value) {
    promises.push(userBookStore.patchUserBookReadDate(props.book.id, readDate.value))
  }

  Promise.all(promises)
    .then(async () => {
      if (reviewForm.value.content.trim()) {
        await loadBookReviews()
      }
      resetReviewForm()
      emit('readDateUpdated')
    })
    .catch(() => $q.notify({ message: t('error-occurred'), type: 'negative' }))
    .finally(() => (loading.value = false))
}
</script>
