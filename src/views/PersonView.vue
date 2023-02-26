<template>
  <q-page padding class="non-selectable">
    <div class="flex items-center">
      <h1 class="text-h5 text-primary text-left q-my-none">{{ $t('book.bookcase', [person.shelfName || person.displayName]) }}</h1>
      <q-space />
      <ShelfDialog @sort="sort" v-model="filter" />
    </div>
    <TheShelf :books="filteredBooks" @emitAddID="addBook" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book, User } from '@/models'
import { useBookStore, usePeopleStore } from '@/store'
import { useQuasar } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const peopleStore = usePeopleStore()
const bookStore = useBookStore()

const ascDesc = ref('asc')
const filter = ref('')
const person = ref({} as User)
const sortKey = ref<string | number>('')

onMounted(() => {
  peopleStore.fetchPersonAndBooks()
})

peopleStore.$subscribe((_mutation, state) => {
  person.value = state._person
  document.title = person.value.displayName ? `LivroLog | ${person.value.displayName}` : 'LivroLog'
})

const filteredBooks = computed(() => {
  return person.value.books?.filter(
    (book) =>
      book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.[0].toLowerCase().includes(filter.value.toLowerCase())
  )
})

function sort(label: string | number) {
  sortKey.value = label
  ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'

  const multiplier = ascDesc.value === 'asc' ? 1 : -1

  if (!person.value.books) {
    return
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return person.value.books.sort((a: any, b: any) => {
    if (a[label] > b[label]) return 1 * multiplier
    if (a[label] < b[label]) return -1 * multiplier
    return 0
  })
}

function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  bookStore
    .addBook(book, person.value.uid)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.added-to-shelf') }))
    .catch(() => $q.notify({ icon: 'error', message: t('book.already-exists') }))
}
</script>
