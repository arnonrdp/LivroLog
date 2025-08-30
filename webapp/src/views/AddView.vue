<template>
  <q-page padding>
    <q-form @submit.prevent="search">
      <q-input v-model="seek" class="q-mx-auto" clearable dense :label="$t('addlabel')" style="max-width: 32rem" @clear="clearSearch">
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>
    </q-form>

    <TheLoading v-show="isSearching" />

    <section class="items-baseline justify-center row">
      <figure v-for="(book, index) in books" :key="index" class="relative-position q-mx-md q-my-lg">
        <q-btn 
          :color="isBookInLibrary(book) ? 'positive' : 'primary'" 
          :icon="isBookInLibrary(book) ? 'check' : 'add'" 
          :disable="isBookInLibrary(book)"
          round 
          @click.once="addBook(book)" 
        />
        <div class="book-cover" @click="showBookReviews(book)">
          <img v-if="book.thumbnail" :alt="`Cover of ${book.title}`" :src="book.thumbnail" style="width: 8rem" />
          <img v-else :alt="`No cover available for ${book.title}`" src="@/assets/no_cover.jpg" style="width: 8rem" />
        </div>
        <figcaption style="max-width: 8rem">{{ book.title }}</figcaption>
        <figcaption id="authors" style="max-width: 8rem">
          <span class="text-body2 text-weight-bold">
            {{ book.authors || $t('unknown-author') }}
          </span>
        </figcaption>
      </figure>
    </section>

    <BookDialog v-model="showBookDialog" :book-data="selectedBook" :book-id="selectedBookId" />
  </q-page>
</template>

<script setup lang="ts">
import TheLoading from '@/components/add/TheLoading.vue'
import BookDialog from '@/components/home/BookDialog.vue'
import type { Book } from '@/models'
import { useBookStore, useUserBookStore, useUserStore } from '@/stores'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const bookStore = useBookStore()
const userBookStore = useUserBookStore()
const userStore = useUserStore()

const books = ref<Book[]>([])
const seek = ref('')
const showBookDialog = ref(false)
const isSearching = ref(false)
const selectedBookId = ref<string | undefined>()
const selectedBook = ref<Book | undefined>()

document.title = `LivroLog | ${t('add')}`

async function search() {
  books.value = []
  isSearching.value = true
  books.value = (await bookStore.getBooks({ search: seek.value }).finally(() => (isSearching.value = false))) || []
}

async function addBook(book: Book) {
  await userBookStore.postUserBooks(book)
}

function clearSearch() {
  seek.value = ''
  books.value = []
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
</style>
