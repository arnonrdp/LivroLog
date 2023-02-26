<template>
  <q-page padding class="non-selectable">
    <div class="flex items-center">
      <h1 class="text-h5 text-primary text-left q-my-none">{{ $t('book.bookcase', [displayName]) }}</h1>
      <q-space />
      <ShelfDialog @sort="sort" v-model="filter" />
    </div>
    <TheShelf :books="filteredBooks" @emitRemoveID="removeBook" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book } from '@/models'
import { useBookStore, useUserStore } from '@/store'
import { useQuasar } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const userStore = useUserStore()
const bookStore = useBookStore()

const ascDesc = ref('asc')
const books = ref([] as Book[])
const displayName = userStore.getUser.displayName
const filter = ref('')
const sortKey = ref<string | number>('')

onMounted(() => {
  bookStore.fetchBooks()
})

bookStore.$subscribe((_mutation, state) => {
  books.value = state._books
})

const filteredBooks = computed(() => {
  return books.value.filter(
    (book) =>
      book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.[0].toLowerCase().includes(filter.value.toLowerCase())
  )
})

function sort(label: string | number) {
  sortKey.value = label
  ascDesc.value = ascDesc.value === 'asc' ? 'desc' : 'asc'

  const multiplier = ascDesc.value === 'asc' ? 1 : -1

  if (!books.value) {
    return
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return books.value.sort((a: any, b: any) => {
    if (a[label] > b[label]) return 1 * multiplier
    if (a[label] < b[label]) return -1 * multiplier
    return 0
  })
}

function removeBook(id: Book['id']) {
  bookStore
    .removeBook(id)
    .then(() => $q.notify({ icon: 'check_circle', message: t('book.removed-success') }))
    .catch(() => $q.notify({ icon: 'error', message: t('book.removed-error') }))
}
</script>
