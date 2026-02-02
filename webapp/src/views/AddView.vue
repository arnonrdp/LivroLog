<template>
  <q-page padding>
    <q-form @submit.prevent="search">
      <q-input
        v-model="seek"
        class="q-mx-auto"
        clearable
        data-testid="book-search-input"
        dense
        :label="$t('addlabel')"
        style="max-width: 32rem"
        @clear="clearSearch"
        @keyup.enter="search"
      >
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
        <template v-slot:append>
          <q-btn color="primary" data-testid="book-search-button" dense flat icon="search" round type="submit" @click="search" />
        </template>
      </q-input>
    </q-form>

    <TheLoading v-show="isSearching" />

    <section class="items-baseline justify-center row">
      <figure v-for="(book, index) in books" :key="index" class="relative-position q-mx-md q-my-lg" data-testid="book-result">
        <q-btn
          :color="isBookInLibrary(book) ? 'positive' : 'primary'"
          data-testid="add-book-btn"
          :disable="isBookInLibrary(book)"
          :icon="isBookInLibrary(book) ? 'check' : 'add'"
          round
          @click.once="addBook(book)"
        />
        <div class="book-cover" @click="showBookReviews(book)">
          <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" style="width: 8rem" />
          <BookCoverPlaceholder v-else size="md" :title="book.title" />
        </div>
        <figcaption style="max-width: 8rem">{{ book.title }}</figcaption>
        <figcaption id="authors" style="max-width: 8rem">
          <span class="text-body2 text-weight-bold">
            {{ book.authors || $t('unknown-author') }}
          </span>
        </figcaption>
      </figure>
    </section>

    <!-- Add Your Own Book Section -->
    <div v-if="hasSearched && !isSearching" class="add-own-book-section" data-testid="add-own-book-section">
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

    <BookDialog v-model="showBookDialog" :book-data="selectedBook" :book-id="selectedBookId" />
  </q-page>
</template>

<script setup lang="ts">
import TheLoading from '@/components/add/TheLoading.vue'
import BookCoverPlaceholder from '@/components/common/BookCoverPlaceholder.vue'
import BookDialog from '@/components/home/BookDialog.vue'
import type { Book } from '@/models'
import { useBookStore, useUserBookStore, useUserStore } from '@/stores'
import api from '@/utils/axios'
import { Notify } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

const bookStore = useBookStore()
const userBookStore = useUserBookStore()
const userStore = useUserStore()

const books = ref<Book[]>([])
const seek = ref('')
const showBookDialog = ref(false)
const isSearching = ref(false)
const selectedBookId = ref<string | undefined>()
const selectedBook = ref<Book | undefined>()
const hasSearched = ref(false)

// Add book from Amazon dialog state
const showAddBookDialog = ref(false)
const amazonUrl = ref('')
const amazonUrlError = ref('')
const isAddingBook = ref(false)

document.title = `LivroLog | ${t('add')}`

async function search() {
  books.value = []
  isSearching.value = true
  hasSearched.value = true
  books.value = (await bookStore.getBooks({ search: seek.value }).finally(() => (isSearching.value = false))) || []
}

async function addBook(book: Book) {
  await userBookStore.postUserBooks(book)
}

function clearSearch() {
  seek.value = ''
  books.value = []
  hasSearched.value = false
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

function isBookInLibrary(book: Book): boolean {
  const userBooks = userStore.me.books || []
  return userBooks.some((userBook) => {
    // Check by internal ID (if book.id exists and is internal)
    if (book.id && book.id.startsWith('B-') && userBook.id === book.id) {
      return true
    }
    // Check by google_id (most reliable for external books)
    if (book.google_id && userBook.google_id === book.google_id) {
      return true
    }
    // Legacy check: if book.id is actually a google_id
    if (book.id && !book.id.startsWith('B-') && userBook.google_id === book.id) {
      return true
    }
    return false
  })
}

function showBookReviews(book: Book) {
  // If the book has an ID (it's in our database), pass the ID
  // Otherwise, pass the entire book data for display
  if (book.id) {
    selectedBookId.value = book.id
    selectedBook.value = undefined
  } else {
    selectedBookId.value = undefined
    selectedBook.value = book
  }
  showBookDialog.value = true
}
</script>

<style scoped lang="sass">
figure
  & button
    opacity: 0
    position: absolute
    right: -1.5rem
    top: -1rem
    visibility: hidden
    z-index: 1
  &:hover button, & button:hover
    opacity: 1
    transition: 0.5s
    visibility: visible

.book-cover
  cursor: pointer
  transition: transform 0.2s ease

  &:hover
    transform: scale(1.05)

.add-own-book-section
  margin-top: 2rem
  max-width: 600px
  margin-left: auto
  margin-right: auto

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
