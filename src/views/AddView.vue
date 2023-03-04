<template>
  <q-page padding>
    <q-input clearable dense :label="$t('book.addlabel')" v-model="seek" @clear="clearSearch" @keyup.enter="search()">
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
    </q-input>
    <TheLoading v-show="bookStore.isLoading" />
    <div id="results">
      <figure v-for="(book, index) in books" :key="index">
        <q-btn round color="primary" icon="add" @click.once="addBook(book)" />
        <a>
          <img v-if="book.thumbnail" :src="book.thumbnail" alt="" />
          <img v-else src="@/assets/no_cover.jpg" alt="" />
        </a>
        <figcaption>{{ book.title }}</figcaption>
        <figcaption id="authors">
          <span v-for="(author, i) in book.authors" :key="i">
            <span class="text-body2 text-weight-bold">{{ author }}</span>
            <span v-if="book.authors && i + 1 < book.authors.length">,</span>
          </span>
          <span v-if="!book.authors?.length" class="text-body2 text-weight-bold">{{ $t('book.unknown-author') }}</span>
        </figcaption>
      </figure>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import TheLoading from '@/components/add/TheLoading.vue'
import type { Book } from '@/models'
import { useBookStore, useUserStore } from '@/store'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const userStore = useUserStore()
const bookStore = useBookStore()

const books = ref<Book[]>([])
const seek = ref('')

document.title = `LivroLog | ${t('menu.add')}`

async function search() {
  books.value = []
  await bookStore.searchBookOnGoogle(seek.value)
  books.value = bookStore.getSearchResults
}

function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  bookStore
    .addBook(book, userStore.getUser.uid)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.added-to-shelf') }))
    .catch((error) => $q.notify({ icon: 'error', message: errorMessages[error] }))
}

function clearSearch() {
  seek.value = ''
  books.value = []
}

const errorMessages: { [key: string]: string } = {
  book_already_exists: t('book.already-exists')
}
</script>

<style scoped>
.q-input {
  margin: auto;
  max-width: 32rem;
}

#results {
  align-items: baseline;
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
}

figure {
  padding-top: 5px;
  position: relative;
}

figure button {
  opacity: 0;
  position: absolute;
  right: -1.5rem;
  top: -1rem;
  visibility: hidden;
  z-index: 1;
}

figure:hover button,
figure button:hover {
  opacity: 1;
  transition: 0.5s;
  visibility: visible;
}

#results img {
  width: 8rem;
}

figcaption {
  max-width: 8rem;
}
</style>
