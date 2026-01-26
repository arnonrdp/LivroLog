<template>
  <q-page class="search-page q-pa-md">
    <!-- Search Header -->
    <div class="search-header">
      <q-input
        v-model="searchQuery"
        autofocus
        class="search-input"
        clearable
        dense
        outlined
        :placeholder="$t('search.placeholder')"
        rounded
        @clear="clearSearch"
        @keydown.enter="performSearch"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append>
          <q-btn v-if="searchQuery" color="primary" dense flat :label="$t('search.button')" no-caps @click="performSearch" />
        </template>
      </q-input>
    </div>

    <!-- Results Title -->
    <div v-if="hasSearched && !isLoading" class="results-header">
      <h2 v-if="books.length > 0">
        {{ $t('search.results-for', { query: lastSearchQuery }) }}
      </h2>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <div class="skeleton-grid">
        <q-skeleton v-for="n in 12" :key="n" class="skeleton-card" height="200px" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="hasSearched && books.length === 0" class="empty-state">
      <q-icon color="grey-5" name="search_off" size="4rem" />
      <h3>{{ $t('search.no-results') }}</h3>
      <p>{{ $t('search.no-results-hint') }}</p>
    </div>

    <!-- Initial State -->
    <div v-else-if="!hasSearched" class="initial-state">
      <q-icon color="grey-4" name="auto_stories" size="5rem" />
      <h3>{{ $t('search.initial-title') }}</h3>
      <p>{{ $t('search.initial-hint') }}</p>
    </div>

    <!-- Results Grid -->
    <div v-else class="results-grid">
      <div v-for="(book, idx) in books" :key="book.id || idx" class="book-card" @click="navigateToBook(book)">
        <div class="book-cover">
          <q-img v-if="book.thumbnail" :alt="book.title" fit="cover" :ratio="2 / 3" :src="book.thumbnail">
            <template v-slot:error>
              <BookCoverPlaceholder :title="book.title" />
            </template>
          </q-img>
          <BookCoverPlaceholder v-else :title="book.title" />
        </div>
        <div class="book-info">
          <h4 class="book-title">{{ book.title }}</h4>
          <p class="book-author">{{ book.authors || $t('search.unknown-author') }}</p>
        </div>
      </div>
    </div>

    <!-- Add Your Own Book Section (for authenticated users only) -->
    <div v-if="hasSearched && !isLoading && authStore.isAuthenticated" class="add-own-book-section" data-testid="add-own-book-section">
      <q-separator class="q-my-lg" />
      <div class="add-own-book-content">
        <p class="add-own-book-text">{{ $t('search.not-found-add-own') }}</p>
        <q-btn color="primary" data-testid="add-own-book-btn" no-caps outline @click="showAddBookDialog = true">
          {{ $t('search.add-book-button') }}
        </q-btn>
      </div>
    </div>

    <!-- Add Book from Amazon Dialog -->
    <q-dialog v-model="showAddBookDialog">
      <q-card class="add-book-dialog" data-testid="add-book-amazon-dialog">
        <q-card-section>
          <div class="text-h6">{{ $t('search.add-book-title') }}</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <p class="text-body2 q-mb-md">{{ $t('search.add-book-description') }}</p>
          <q-input
            v-model="amazonUrl"
            autofocus
            clearable
            data-testid="amazon-url-input"
            dense
            :error="!!amazonUrlError"
            :error-message="amazonUrlError"
            :label="$t('search.amazon-url-label')"
            outlined
            :placeholder="$t('search.amazon-url-placeholder')"
            @keydown.enter="submitAmazonUrl"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn v-close-popup color="grey" data-testid="cancel-add-book-btn" flat :label="$t('cancel')" no-caps />
          <q-btn
            color="primary"
            data-testid="submit-amazon-url-btn"
            :disable="!amazonUrl || isAddingBook"
            :label="$t('add-book')"
            :loading="isAddingBook"
            no-caps
            @click="submitAmazonUrl"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import BookCoverPlaceholder from '@/components/common/BookCoverPlaceholder.vue'
import type { Book } from '@/models'
import { useAuthStore, useBookStore } from '@/stores'
import api from '@/utils/axios'
import { Notify, useMeta } from 'quasar'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const bookStore = useBookStore()
const authStore = useAuthStore()
const { t } = useI18n()

const searchQuery = ref('')
const lastSearchQuery = ref('')
const books = ref<Book[]>([])
const isLoading = ref(false)
const hasSearched = ref(false)

// Add book from Amazon dialog state
const showAddBookDialog = ref(false)
const amazonUrl = ref('')
const amazonUrlError = ref('')
const isAddingBook = ref(false)

const baseUrl = import.meta.env.VITE_FRONTEND_URL || 'https://livrolog.com'

// Dynamic SEO Meta Tags
const pageTitle = computed(() => {
  if (lastSearchQuery.value) {
    return `"${lastSearchQuery.value}" - ${t('search.button')} | LivroLog`
  }
  return `${t('search.initial-title')} | LivroLog`
})

const pageDescription = computed(() => {
  if (lastSearchQuery.value && books.value.length > 0) {
    return t('search.results-for', { query: lastSearchQuery.value }) + ` - ${books.value.length} ${t('books', books.value.length)}`
  }
  return t('search.initial-hint')
})

useMeta(() => ({
  title: pageTitle.value,
  meta: {
    description: { name: 'description', content: pageDescription.value },
    ogType: { property: 'og:type', content: 'website' },
    ogTitle: { property: 'og:title', content: pageTitle.value },
    ogDescription: { property: 'og:description', content: pageDescription.value },
    ogUrl: { property: 'og:url', content: `${baseUrl}/search${lastSearchQuery.value ? '?q=' + encodeURIComponent(lastSearchQuery.value) : ''}` },
    twitterCard: { name: 'twitter:card', content: 'summary' },
    twitterTitle: { name: 'twitter:title', content: pageTitle.value },
    twitterDescription: { name: 'twitter:description', content: pageDescription.value },
    robots: { name: 'robots', content: lastSearchQuery.value ? 'noindex, follow' : 'index, follow' }
  }
}))

// Watch for query param changes
watch(
  () => route.query.q,
  (newQuery) => {
    if (newQuery && typeof newQuery === 'string') {
      searchQuery.value = newQuery
      performSearch()
    }
  },
  { immediate: true }
)

onMounted(() => {
  // If there's a query param, search immediately
  if (route.query.q && typeof route.query.q === 'string') {
    searchQuery.value = route.query.q
    performSearch()
  }
})

function performSearch() {
  if (!searchQuery.value.trim()) return

  isLoading.value = true
  hasSearched.value = true
  lastSearchQuery.value = searchQuery.value.trim()

  // Update URL with search query
  router.replace({ query: { q: searchQuery.value.trim() } })

  bookStore
    .getBooks({ search: searchQuery.value.trim() })
    .then((response) => {
      books.value = response || []
    })
    .catch((error) => {
      console.error('Search error:', error)
      Notify.create({ message: 'Error searching books', type: 'negative' })
      books.value = []
    })
    .finally(() => {
      isLoading.value = false
    })
}

function clearSearch() {
  searchQuery.value = ''
  books.value = []
  hasSearched.value = false
  router.replace({ query: {} })
}

function navigateToBook(book: Book) {
  // Check if book has an internal ID (starts with 'B-')
  if (book.id && book.id.startsWith('B-')) {
    // Book already exists in database
    router.push(`/books/${book.id}`)
  } else {
    // Book from external source (Amazon), need to create it first
    isLoading.value = true

    // Prepare book data for creation (include all Amazon data from search)
    const bookData = {
      title: book.title,
      authors: book.authors,
      isbn: book.isbn || book.ISBN,
      thumbnail: book.thumbnail,
      description: book.description,
      publisher: book.publisher,
      language: book.language,
      page_count: book.page_count,
      google_id: book.google_id,
      amazon_asin: book.amazon_asin,
      amazon_rating: book.amazon_rating,
      amazon_rating_count: book.amazon_rating_count
    }

    bookStore
      .postBook(bookData)
      .then((response) => {
        // API returns { book: ..., enriched: ..., message: ... }
        const createdBook = response?.book || response
        if (createdBook?.id) {
          router.push(`/books/${createdBook.id}`)
        } else {
          console.error('Created book has no ID:', response)
          Notify.create({ message: 'Error loading book details', type: 'negative' })
        }
      })
      .catch((error) => {
        console.error('Error creating book:', error)
        Notify.create({ message: 'Error loading book details', type: 'negative' })
      })
      .finally(() => {
        isLoading.value = false
      })
  }
}

function submitAmazonUrl() {
  if (!amazonUrl.value.trim()) return

  amazonUrlError.value = ''
  isAddingBook.value = true

  api
    .post('/user/books/from-amazon', {
      amazon_url: amazonUrl.value.trim(),
      reading_status: 'read'
    })
    .then((response) => {
      const data = response.data
      if (data.success) {
        showAddBookDialog.value = false
        amazonUrl.value = ''
        Notify.create({ message: t('search.book-added-success'), type: 'positive' })
        // Navigate to the book page
        if (data.book?.id) {
          router.push(`/books/${data.book.id}`)
        }
      } else {
        amazonUrlError.value = data.message || t('error-occurred')
      }
    })
    .catch((error) => {
      const message = error.response?.data?.message || t('error-occurred')
      amazonUrlError.value = message
    })
    .finally(() => {
      isAddingBook.value = false
    })
}
</script>

<style scoped lang="sass">
.search-page
  max-width: 1400px
  margin: 0 auto

.search-header
  margin-bottom: 2rem
  max-width: 600px
  margin-left: auto
  margin-right: auto

.search-input
  :deep(.q-field__control)
    background: white
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08)

.results-header
  margin-bottom: 1.5rem

  h2
    color: #1a1a2e
    font-size: 1.25rem
    font-weight: 600
    margin: 0

.loading-container,
.empty-state,
.initial-state
  padding: 3rem 1rem
  text-align: center

.skeleton-grid
  display: grid
  gap: 1.5rem
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr))

.skeleton-card
  border-radius: 8px

.empty-state,
.initial-state
  h3
    color: #4a4a68
    font-size: 1.25rem
    font-weight: 600
    margin: 1rem 0 0.5rem

  p
    color: #6b6b8d
    font-size: 0.95rem
    margin: 0

.results-grid
  display: grid
  gap: 1.5rem
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr))

  @media (min-width: 768px)
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr))

.book-card
  cursor: pointer
  transition: transform 0.2s ease

  &:hover
    transform: translateY(-4px)

    .book-cover
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15)

.book-cover
  background: #f0f0f0
  border-radius: 8px
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1)
  overflow: hidden
  transition: box-shadow 0.2s ease
  aspect-ratio: 2 / 3

  :deep(.book-placeholder)
    width: 100%
    height: 100%

.book-info
  padding: 0.75rem 0

.book-title
  -webkit-box-orient: vertical
  -webkit-line-clamp: 2
  color: #1a1a2e
  display: -webkit-box
  font-size: 0.9rem
  font-weight: 600
  line-height: 1.3
  margin: 0 0 0.25rem
  overflow: hidden

.book-author
  -webkit-box-orient: vertical
  -webkit-line-clamp: 1
  color: #6b6b8d
  display: -webkit-box
  font-size: 0.8rem
  margin: 0
  overflow: hidden

.add-own-book-section
  margin-top: 2rem

.add-own-book-content
  text-align: center
  padding: 1.5rem 1rem

  .add-own-book-text
    color: #6b6b8d
    font-size: 1rem
    margin: 0 0 1rem

.add-book-dialog
  min-width: 320px
  max-width: 450px
  width: 100%
</style>
