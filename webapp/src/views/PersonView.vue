<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ person.shelf_name || person.display_name }}</h1>
      <q-space />
      <ShelfDialog v-model="filter" :sort-key="sortKey" :asc-desc="ascDesc" @sort="onSort" />
    </div>
    <TheShelf :books="filteredBooks" @emitAddID="addBook" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book, User } from '@/models'
import { useBookStore, usePeopleStore } from '@/stores'
import { sortBooks } from '@/utils'
import { useQuasar } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const $q = useQuasar()
const { t } = useI18n()
const route = useRoute()

const peopleStore = usePeopleStore()
const bookStore = useBookStore()

const ascDesc = ref('desc')
const sortKey = ref<string | number>('readIn')
const filter = ref('')
const person = ref({} as User)

const filteredBooks = computed(() => {
  const filtered = (person.value.books || []).filter(
    (book: Book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
  )
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

peopleStore.$subscribe((_mutation, state) => {
  person.value = state._person
  document.title = person.value.display_name ? `LivroLog | ${person.value.display_name}` : 'LivroLog'
})

onMounted(() => {
  const username = route.params.username as string
  if (username) {
    peopleStore.getUserByIdentifier(username)
  }
})

function onSort(label: string | number) {
  if (sortKey.value === label) {
    ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = label
    ascDesc.value = 'asc'
  }
}

function addBook(book: Book) {
  book = { ...book, addedIn: Date.now(), readIn: '' }
  bookStore
    .postBook(book)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.added-to-shelf') }))
    .catch(() => $q.notify({ icon: 'error', message: t('book.already-exists') }))
}
</script>
