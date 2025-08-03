<template>
  <q-page padding>
    <q-form @submit.prevent="search">
      <q-input v-model="seek" clearable class="q-mx-auto" dense :label="$t('book.addlabel')" @clear="clearSearch" style="max-width: 32rem">
        <template v-slot:prepend>
          <q-icon name="search" />
        </template>
      </q-input>
    </q-form>

    <TheLoading v-show="isSearching" />

    <section class="items-baseline justify-center row">
      <figure v-for="(book, index) in books" class="relative-position q-mx-md q-my-lg" :key="index">
        <q-btn color="primary" icon="add" round @click.once="addBook(book)" />
        <a>
          <img
            v-if="book.thumbnail"
            :alt="`Cover of ${book.title}`"
            :class="{ 'cursor-pointer': book.description }"
            :src="book.thumbnail"
            style="width: 8rem"
            @click="book.description && showBookSummary(book)"
          />
          <img
            v-else
            :alt="`No cover available for ${book.title}`"
            :class="{ 'cursor-pointer': book.description }"
            src="@/assets/no_cover.jpg"
            style="width: 8rem"
            @click="book.description && showBookSummary(book)"
          />
        </a>
        <figcaption style="max-width: 8rem">{{ book.title }}</figcaption>
        <figcaption id="authors" style="max-width: 8rem">
          <span class="text-body2 text-weight-bold">
            {{ book.authors || $t('book.unknown-author') }}
          </span>
        </figcaption>
      </figure>
    </section>

    <q-dialog v-model="isDialogOpen" class="book-summary-dialog">
      <q-card bordered>
        <q-card-section>
          <h3>{{ selectedBook?.title }}</h3>
          <q-separator />
          <div class="q-mt-md q-pa-md">
            <div class="book-summary">{{ selectedBook?.description }}</div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn v-close-popup color="primary" flat label="Close" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import TheLoading from '@/components/add/TheLoading.vue'
import type { Book } from '@/models'
import { useBookStore } from '@/stores'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const bookStore = useBookStore()

const books = ref<Book[]>([])
const seek = ref('')
const isDialogOpen = ref(false)
const isSearching = ref(false)
const selectedBook = ref<Book | null>(null)

document.title = `LivroLog | ${t('menu.add')}`

async function search() {
  books.value = []
  isSearching.value = true
  await bookStore.getBooksSearch(seek.value).finally(() => (isSearching.value = false))
  books.value = bookStore.searchResults
}

async function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  await bookStore.postBook(book)
}

function clearSearch() {
  seek.value = ''
  books.value = []
}

function showBookSummary(book: Book) {
  selectedBook.value = book
  isDialogOpen.value = !!selectedBook.value?.description
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
</style>
