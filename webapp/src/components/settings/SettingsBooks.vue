<template>
  <p>{{ $t('books-description') }}</p>
  <p v-if="books?.length == 0">
    {{ $t('bookshelf-empty') }}
    <router-link to="/add">{{ $t('bookshelf-add-few') }}</router-link>
  </p>
  <table v-else class="q-mx-auto">
    <thead>
      <tr>
        <th>{{ $t('column-title') }}</th>
        <th>{{ $t('column-readIn') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="book in books" :key="book.id">
        <td class="text-left">{{ book.title }}</td>
        <td>
          <q-input class="q-ml-xs" dense mask="####-##-##" :model-value="readDates[book.id]" readonly>
            <template v-slot:append>
              <q-icon class="cursor-pointer" name="event">
                <q-popup-proxy ref="qDateProxy">
                  <q-date v-model="readDates[book.id]" mask="YYYY-MM-DD" minimal />
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import { useBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const bookStore = useBookStore()
const userStore = useUserStore()

document.title = `LivroLog | ${t('books')}`

const readDates = ref<Record<string, string>>({})
const originalDates = ref<Record<string, string>>({})
const isUpdating = ref<Record<string, boolean>>({})

const books = computed(() => {
  const filtered = userStore.me.books?.filter((book) => book) || []
  return sortBooks(filtered, 'read_at', 'desc')
})

watch(
  () => userStore.me.books,
  () => {
    const dates: Record<string, string> = {}
    const originals: Record<string, string> = {}
    books.value.forEach((book) => {
      const existingDate = book.pivot?.read_at || ''
      const dateOnly = existingDate ? existingDate.substring(0, 10) : ''
      dates[book.id] = dateOnly
      originals[book.id] = dateOnly
    })
    readDates.value = dates
    originalDates.value = originals
  },
  { immediate: true, deep: true }
)

watch(
  readDates,
  async (newDates) => {
    for (const bookId in newDates) {
      const newDate = newDates[bookId]
      const originalDate = originalDates.value[bookId] || ''

      // Only update if date actually changed and we're not already updating this book
      if (newDate !== originalDate && !isUpdating.value[bookId]) {
        const dateOnly = newDate ? newDate.substring(0, 10) : ''

        isUpdating.value[bookId] = true
        await bookStore
          .patchUserBookReadDate(bookId, dateOnly)
          .then(() => (originalDates.value[bookId] = dateOnly))
          .catch(() => (readDates.value[bookId] = originalDate))
          .finally(() => (isUpdating.value[bookId] = false))
      }
    }
  },
  { deep: true }
)
</script>
