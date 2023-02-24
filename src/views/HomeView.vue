<template>
  <q-page padding class="non-selectable">
    <TheShelf :shelfName="displayName" :books="books" @emitRemoveID="removeBook" />
  </q-page>
</template>

<script setup lang="ts">
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book } from '@/models'
import { useBookStore, useUserStore } from '@/store'
import { useMeta, useQuasar } from 'quasar'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const userStore = useUserStore()
const bookStore = useBookStore()
const $q = useQuasar()
const { t } = useI18n()

const displayName = userStore.getUser.displayName
const books = ref(bookStore.getBooks)

bookStore.fetchBooks()

useMeta({
  title: `LivroLog | ${t('menu.home')}`,
  meta: {
    ogTitle: { name: 'og:title', content: `LivroLog | ${t('menu.home')}` },
    twitterTitle: { name: 'twitter:title', content: `LivroLog | ${t('menu.home')}` }
  }
})

watch(
  () => bookStore.getBooks,
  () => (books.value = bookStore.getBooks)
)

// bookStore
//   .compareMyModifiedAt()
//   .then(async (equals) => {
//     if (!equals | !this.getMyBooks.length) await this.$store.dispatch('queryDBMyBooks')
//     this.books = this.getMyBooks
//   })
//   .catch((err) => console.error('err: ', err))

function removeBook(id: Book['id']) {
  bookStore
    .removeBook(id)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.removed-success') }))
    .catch(() => $q.notify({ icon: 'error', message: t('book.removed-error') }))
}
</script>
