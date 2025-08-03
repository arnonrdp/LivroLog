<template>
  <q-page class="non-selectable" padding>
    <div class="flex items-center">
      <h1 class="text-primary text-left q-my-none">{{ authStore.user.display_name }}</h1>
      <q-space />
      <ShelfDialog v-model="filter" :sort-key="sortKey" :asc-desc="ascDesc" @sort="onSort" />
    </div>
    <TheShelf :books="filteredBooks" @emitRemoveID="removeBook" />
  </q-page>
</template>

<script setup lang="ts">
import ShelfDialog from '@/components/home/ShelfDialog.vue'
import TheShelf from '@/components/home/TheShelf.vue'
import type { Book } from '@/models'
import { useAuthStore, useBookStore, usePeopleStore } from '@/stores'
import { sortBooks } from '@/utils'
import { useQuasar } from 'quasar'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const authStore = useAuthStore()
const bookStore = useBookStore()
const peopleStore = usePeopleStore()

const ascDesc = ref('desc')
const sortKey = ref<string | number>('readIn')
const filter = ref('')

const books = computed(() => peopleStore.person.books || [])

const filteredBooks = computed(() => {
  const filtered = books.value.filter(
    (book) => book.title.toLowerCase().includes(filter.value.toLowerCase()) || book.authors?.toLowerCase().includes(filter.value.toLowerCase())
  )
  return sortBooks(filtered, sortKey.value, ascDesc.value)
})

onMounted(async () => {
  if (authStore.user.id) {
    await peopleStore.getUserByIdentifier(authStore.user.id)
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

async function removeBook(id: Book['id']) {
  await bookStore.deleteBook(id)
  // Refresh data after deletion
  if (authStore.user.id) {
    await peopleStore.getUserByIdentifier(authStore.user.id)
  }
}
</script>
