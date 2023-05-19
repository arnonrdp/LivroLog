<template>
  <q-page padding>
    <q-table
      class="q-mx-auto"
      :columns="authorsColumns"
      flat
      :loading="bookStore.isLoading"
      :rows="authorsAndQuantities"
      :rows-per-page-options="[]"
      style="width: 30rem; max-width: 90vw"
      title="Autores mais lidos"
      v-model:pagination="authorsPagination"
    >
      <template v-slot:bottom>
        <q-pagination class="q-mx-auto" color="grey-8" :max="authorsPagesNumber" size="sm" v-model="authorsPagination.page" />
      </template>
    </q-table>

    <q-table
      class="q-mt-lg q-mx-auto"
      :columns="booksColumns"
      flat
      :loading="bookStore.isLoading"
      :rows="booksAndQuantities"
      :rows-per-page-options="[]"
      style="width: 30rem; max-width: 90vw"
      title="Livros mais lidos"
      v-model:pagination="booksPagination"
    >
      <template v-slot:bottom>
        <q-pagination class="q-mx-auto" color="grey-8" :max="booksPagesNumber" size="sm" v-model="booksPagination.page" />
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import { useBookStore } from '@/store'
import type { QTableColumn } from 'quasar'
import { onMounted, ref, computed } from 'vue'

const bookStore = useBookStore()

const authorsAndQuantities = ref<{ author: string; count: number }[]>([])
const authorsColumns: QTableColumn<Record<string, number>>[] = [
  { name: 'author', label: 'Autor', field: (row) => row.author, align: 'left', sortable: true },
  { name: 'count', label: 'Quantidade', field: (row) => row.count, sortable: true }
]
const authorsPagesNumber = computed(() => Math.ceil(authorsAndQuantities.value.length / booksPagination.value.rowsPerPage))
const authorsPagination = ref({ descending: true, page: 1, rowsPerPage: 5, sortBy: 'count' })

const booksAndQuantities = ref<{ book: string; count: number }[]>([])
const booksColumns: QTableColumn<Record<string, number>>[] = [
  { name: 'book', label: 'Book', field: (row) => row.book, align: 'left', sortable: true },
  { name: 'count', label: 'Quantidade', field: (row) => row.count, sortable: true }
]
const booksPagesNumber = computed(() => Math.ceil(booksAndQuantities.value.length / booksPagination.value.rowsPerPage))
const booksPagination = ref({ descending: true, page: 1, rowsPerPage: 5, sortBy: 'count' })

const countOccurrences = (items: string[]) => {
  return items.reduce((count, item) => {
    count[item] = (count[item] || 0) + 1
    return count
  }, {} as Record<string, number>)
}

onMounted(async () => {
  await bookStore.fetchBooksCollection()

  const authorCount = countOccurrences(bookStore.getBooksCollection.flatMap((book) => book.authors || []))
  const bookCount = countOccurrences(bookStore.getBooksCollection.map((book) => book.title || ''))

  authorsAndQuantities.value = Object.entries(authorCount).map(([author, count]) => ({ author, count }))
  booksAndQuantities.value = Object.entries(bookCount).map(([book, count]) => ({ book, count }))
})
</script>
