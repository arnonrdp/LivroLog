<template>
  <p>{{ $t('books-description') }}</p>

  <q-table
    class="books-table"
    :columns="columns"
    :dense="$q.screen.lt.md"
    flat
    hide-bottom
    hide-pagination
    row-key="id"
    :rows="books"
    :rows-per-page-options="[0]"
  >
    <template v-slot:no-data>
      <div class="full-width text-center q-py-lg">
        {{ $t('bookshelf-empty') }}
        <router-link to="/add">{{ $t('bookshelf-add-few') }}</router-link>
      </div>
    </template>

    <template v-slot:body-cell-status="props">
      <q-td :props="props">
        <q-select
          v-model="readingStatuses[props.row.id]"
          borderless
          dense
          emit-value
          :loading="isUpdating[props.row.id]"
          map-options
          :options="readingStatusOptions"
        />
      </q-td>
    </template>

    <template v-slot:body-cell-readDate="props">
      <q-td :props="props">
        <q-input
          borderless
          class="read-date-input"
          dense
          :loading="isUpdating[props.row.id]"
          mask="####-##-##"
          :model-value="readDates[props.row.id]"
          readonly
        >
          <template v-slot:append>
            <q-icon class="cursor-pointer" name="event">
              <q-popup-proxy cover transition-hide="scale" transition-show="scale">
                <q-date v-model="readDates[props.row.id]" mask="YYYY-MM-DD" minimal>
                  <div class="row items-center justify-end">
                    <q-btn v-close-popup color="primary" flat :label="$t('close')" />
                  </div>
                </q-date>
              </q-popup-proxy>
            </q-icon>
          </template>
        </q-input>
      </q-td>
    </template>
  </q-table>
</template>

<script setup lang="ts">
import type { Book, ReadingStatus } from '@/models'
import { useUserBookStore, useUserStore } from '@/stores'
import { sortBooks } from '@/utils'
import type { QTableColumn } from 'quasar'
import { useQuasar } from 'quasar'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const $q = useQuasar()
const { t } = useI18n()

const userBookStore = useUserBookStore()
const userStore = useUserStore()

document.title = `LivroLog | ${t('books')}`

const isUpdating = ref<Record<string, boolean>>({})
const originalDates = ref<Record<string, string>>({})
const originalStatuses = ref<Record<string, ReadingStatus>>({})
const readDates = ref<Record<string, string>>({})
const readingStatuses = ref<Record<string, ReadingStatus>>({})

const columns = computed<QTableColumn<Book>[]>(() => [
  { name: 'title', label: t('column-title'), field: 'title', align: 'left', sortable: true },
  {
    name: 'status',
    label: t('column-status'),
    field: (row: Book) => row.pivot?.reading_status,
    align: 'left',
    sortable: true,
    style: 'width: 140px'
  },
  {
    name: 'readDate',
    label: t('column-readIn'),
    field: (row: Book) => row.pivot?.read_at,
    align: 'left',
    sortable: true,
    style: 'width: 160px; min-width: 160px'
  }
])

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

onMounted(() => {
  userBookStore.getUserBooks()
})

watch(
  () => userStore.me.books,
  () => {
    const dates: Record<string, string> = {}
    const dateOriginals: Record<string, string> = {}
    const statuses: Record<string, ReadingStatus> = {}
    const statusOriginals: Record<string, ReadingStatus> = {}
    books.value.forEach((book) => {
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

watch(
  readDates,
  (newDates) => {
    for (const bookId in newDates) {
      const newDate = newDates[bookId]
      const originalDate = originalDates.value[bookId] || ''

      if (newDate !== originalDate && !isUpdating.value[bookId]) {
        const dateOnly = newDate ? newDate.substring(0, 10) : ''

        isUpdating.value[bookId] = true
        userBookStore
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
  (newStatuses) => {
    for (const bookId in newStatuses) {
      const newStatus = newStatuses[bookId] || 'read'
      const originalStatus = originalStatuses.value[bookId] || 'read'

      if (newStatus !== originalStatus && !isUpdating.value[bookId]) {
        isUpdating.value[bookId] = true
        userBookStore
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

<style scoped lang="sass">
.books-table
  max-width: 800px
  margin: 0 auto

.read-date-input
  min-width: 140px
</style>
