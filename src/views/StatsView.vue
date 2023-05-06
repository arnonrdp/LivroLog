<template>
  <q-page padding>
    <q-table
      class="q-mx-auto"
      :columns="columns"
      flat
      :loading="bookStore.isLoading"
      :rows="authorAndQuantities"
      :rows-per-page-options="[]"
      style="width: 30rem; max-width: 90vw"
      title="Autores mais lidos"
      v-model:pagination="pagination"
    >
      <template v-slot:bottom>
        <q-pagination class="q-mx-auto" color="grey-8" :max="pagesNumber" size="sm" v-model="pagination.page" />
      </template>
    </q-table>
  </q-page>
</template>

<script setup lang="ts">
import { useBookStore } from '@/store'
import type { QTableColumn } from 'quasar'
import { onMounted, ref, computed } from 'vue'

const bookStore = useBookStore()

const authorAndQuantities = ref<{ author: string; count: number }[]>([])
const columns: QTableColumn<Record<string, number>>[] = [
  { name: 'author', label: 'Autor', field: (row) => row.author, align: 'left', sortable: true },
  { name: 'count', label: 'Quantidade', field: (row) => row.count, sortable: true }
]
const pagesNumber = computed(() => Math.ceil(authorAndQuantities.value.length / pagination.value.rowsPerPage))
const pagination = ref({ descending: true, page: 1, rowsPerPage: 5, sortBy: 'count' })

onMounted(async () => {
  await bookStore.fetchBooksCollection()

  const authorCount = bookStore.getBooksCollection.reduce((count, book) => {
    if (book.authors) {
      for (const author of book.authors) {
        count[author] = (count[author] || 0) + 1
      }
    }
    return count
  }, {} as Record<string, number>)

  authorAndQuantities.value = Object.entries(authorCount).map(([author, count]) => ({ author, count }))
})
</script>
