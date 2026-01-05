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
        <th>{{ $t('column-status') }}</th>
        <th>{{ $t('column-readIn') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="book in books" :key="book.id">
        <td class="text-left q-pr-md">{{ book.title }}</td>
        <td class="q-pr-md">
          <q-select v-model="readingStatuses[book.id]" dense emit-value map-options :options="readingStatusOptions" />
        </td>
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
import type { ReadingStatus } from '@/models'
import { useUserBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const userBookStore = useUserBookStore()
const userStore = useUserStore()

document.title = `LivroLog | ${t('books')}`

const readDates = ref<Record<string, string>>({})
const originalDates = ref<Record<string, string>>({})
const readingStatuses = ref<Record<string, ReadingStatus>>({})
const originalStatuses = ref<Record<string, ReadingStatus>>({})
const isUpdating = ref<Record<string, boolean>>({})

const readingStatusOptions = computed(() => [
  { label: t('want-to-read'), value: 'want_to_read' },
  { label: t('on-hold'), value: 'on_hold' },
  { label: t('reading'), value: 'reading' },
  { label: t('read'), value: 'read' },
  { label: t('re-reading'), value: 're_reading' },
  { label: t('abandoned'), value: 'abandoned' }
])

const books = computed(() => {
  const filtered = userStore.me.books?.filter((book) => book) || []
  return sortBooks(filtered, 'read_at', 'desc')
})

watch(
  () => userStore.me.books,
  () => {
    const dates: Record<string, string> = {}
    const dateOriginals: Record<string, string> = {}
    const statuses: Record<string, ReadingStatus> = {}
    const statusOriginals: Record<string, ReadingStatus> = {}
    books.value.forEach((book) => {
      // Extract read_at and reading_status from pivot data returned by GET /user/books
      const readDate = book.pivot?.read_at || ''
      dates[book.id] = readDate
      dateOriginals[book.id] = readDate

      const status = book.pivot?.reading_status || 'read'
      statuses[book.id] = status
      statusOriginals[book.id] = status
    })
    readDates.value = dates
    originalDates.value = dateOriginals
    readingStatuses.value = statuses
    originalStatuses.value = statusOriginals
  },
  { immediate: true, deep: true }
)

onMounted(() => {
  userBookStore.getUserBooks()
})

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
        await userBookStore
          .patchUserBook(bookId, { read_at: dateOnly })
          .then(() => (originalDates.value[bookId] = dateOnly))
          .catch(() => (readDates.value[bookId] = originalDate))
          .finally(() => (isUpdating.value[bookId] = false))
      }
    }
  },
  { deep: true }
)

watch(
  readingStatuses,
  async (newStatuses) => {
    for (const bookId in newStatuses) {
      const newStatus = newStatuses[bookId] || 'read'
      const originalStatus = originalStatuses.value[bookId] || 'read'

      // Only update if status actually changed and we're not already updating this book
      if (newStatus !== originalStatus && !isUpdating.value[bookId]) {
        isUpdating.value[bookId] = true
        await userBookStore
          .patchUserBook(bookId, { reading_status: newStatus })
          .then(() => (originalStatuses.value[bookId] = newStatus))
          .catch(() => (readingStatuses.value[bookId] = originalStatus))
          .finally(() => (isUpdating.value[bookId] = false))
      }
    }
  },
  { deep: true }
)
</script>
