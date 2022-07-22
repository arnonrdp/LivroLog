<template>
  <q-page padding>
    <q-input v-model="seek" :label="$t('book.addlabel')" @keyup.enter="search()" dense>
      <template v-slot:prepend>
        <q-icon name="search" />
      </template>
      <template v-slot:append>
        <q-icon name="close" @click="clearSearch" class="cursor-pointer" />
      </template>
    </q-input>
    <TheLoading v-show="loading" />
    <div id="results">
      <figure v-for="(book, index) in books" :key="index">
        <q-btn round color="primary" icon="add" @click.once="addBook(book)" />
        <a>
          <img v-if="book.thumbnail" :src="book.thumbnail" alt="" />
          <img v-else src="../assets/no_cover.jpg" alt="" />
        </a>
        <figcaption>{{ book.title }}</figcaption>
        <figcaption id="authors">
          <span v-for="(author, i) in book.authors" :key="i">
            <span class="text-body2 text-weight-bold">{{ author }}</span>
            <span v-if="book.authors && i + 1 < book.authors.length">,</span>
          </span>
        </figcaption>
      </figure>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import TheLoading from '@/components/TheLoading.vue'
import type { Book, GoogleBook } from '@/models'
import { useBookStore, useUserStore } from '@/store'
import axios from 'axios'
import { useMeta, useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const userStore = useUserStore()
const bookStore = useBookStore()
const $q = useQuasar()
const { t } = useI18n()

const loading = ref(false)
const seek = ref('')
const books = ref<Book[]>([])

useMeta({
  title: `Livrero | ${t('menu.add')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `Livrero | ${t('menu.add')}` },
    twitterTitle: { name: 'twitter:title', content: `Livrero | ${t('menu.add')}` }
  }
})

function search() {
  loading.value = true
  books.value = []
  axios
    .get(`https://www.googleapis.com/books/v1/volumes?q=${seek.value}&maxResults=40&printType=books`)
    .then((response) => {
      response.data.items.map((item: GoogleBook) =>
        books.value.push({
          id: item.id,
          title: item.volumeInfo.title || '',
          authors: item.volumeInfo.authors || [t('book.unknown-author')],
          ISBN: item.volumeInfo.industryIdentifiers?.[0].identifier || item.id,
          thumbnail: item.volumeInfo.imageLinks?.thumbnail.replace('http', 'https') || null
        })
      )
    })
    .catch(() => $q.notify({ icon: 'error', message: t('book.no-book-found') }))
    .finally(() => (loading.value = false))
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
  // TODO: Tratar possíveis erros que a Amazon pode retornar
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
