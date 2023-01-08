<template>
  <q-page padding class="non-selectable">
    <TheShelf :shelfName="person.shelfName || person.displayName" :books="person.books!" @emitAddID="addBook" />
  </q-page>
</template>

<script setup lang="ts">
import TheShelf from '@/components/TheShelf.vue'
import { useBookStore, usePeopleStore } from '@/store'
import type { Book } from '@/models'
import { useMeta, useQuasar } from 'quasar'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const peopleStore = usePeopleStore()
const bookStore = useBookStore()
const $q = useQuasar()
const { t } = useI18n()

const person = ref(peopleStore.getPerson)

peopleStore.fetchPersonAndBooks()

watch(
  () => peopleStore.getPerson,
  (newPerson) => (person.value = newPerson)
)

useMeta({
  title: person.value.displayName ? `LivroLog | ${person.value.displayName}` : 'LivroLog',
  meta: {
    ogTitle: { name: 'og:title', content: person.value.displayName ? `LivroLog | ${person.value.displayName}` : 'LivroLog' },
    twitterTitle: { name: 'twitter:title', content: person.value.displayName ? `LivroLog | ${person.value.displayName}` : 'LivroLog' }
  }
})

function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  bookStore
    .addBook(book, person.value.uid)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.added-to-shelf') }))
    .catch(() => $q.notify({ icon: 'error', message: t('book.already-exists') }))
}
</script>
