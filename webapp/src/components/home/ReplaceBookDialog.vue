<template>
  <q-dialog v-model="showDialog">
    <q-card style="max-width: 600px; width: 100%">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ $t('replace-book') }}</div>
        <q-space />
        <q-btn v-close-popup dense flat icon="close" round />
      </q-card-section>

      <q-separator />

      <q-card-section>
        <div class="text-body2 q-mb-md text-grey-8">
          {{ $t('replace-book-description') }}
        </div>

        <!-- Search Bar -->
        <q-input v-model="searchQuery" dense :label="$t('search-books')" outlined @keyup.enter="searchBooks">
          <template #append>
            <q-icon class="cursor-pointer" name="search" @click="searchBooks" />
          </template>
        </q-input>

        <!-- Loading -->
        <div v-if="searching" class="text-center q-py-lg">
          <q-spinner size="32px" />
        </div>

        <!-- Results -->
        <div v-if="searchResults.length > 0" class="q-mt-md">
          <q-list bordered separator>
            <q-item v-for="result in searchResults" :key="result.id" v-ripple clickable @click="selectBook(result)">
              <q-item-section avatar>
                <q-avatar square>
                  <img v-if="result.thumbnail" :src="result.thumbnail" />
                  <q-icon v-else name="book" />
                </q-avatar>
              </q-item-section>

              <q-item-section>
                <q-item-label>{{ result.title }}</q-item-label>
                <q-item-label caption>{{ result.authors }}</q-item-label>
                <q-item-label caption>ISBN: {{ result.isbn || 'N/A' }}</q-item-label>
              </q-item-section>

              <q-item-section side>
                <q-icon name="arrow_forward" />
              </q-item-section>
            </q-item>
          </q-list>
        </div>

        <!-- Empty State -->
        <div v-if="hasSearched && searchResults.length === 0 && !searching" class="text-center q-py-lg text-grey-6">
          <q-icon class="q-mb-sm" name="search_off" size="3em" />
          <div class="text-body2">{{ $t('no-results-found') }}</div>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>

  <!-- Confirmation Dialog -->
  <q-dialog v-model="showConfirmDialog">
    <q-card style="min-width: 350px">
      <q-card-section>
        <div class="text-h6">{{ $t('confirm-replace') }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div class="text-body2">
          {{ $t('confirm-replace-message') }}
        </div>
        <div class="q-mt-md">
          <strong>{{ $t('from') }}:</strong>
          {{ currentBook?.title }}
        </div>
        <div class="q-mt-xs">
          <strong>{{ $t('to') }}:</strong>
          {{ selectedBook?.title }}
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn v-close-popup color="grey-6" flat :label="$t('cancel')" />
        <q-btn color="primary" flat :label="$t('confirm')" :loading="replacing" @click="confirmReplace" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import type { Book } from '@/models'
import { useBookStore, useUserBookStore } from '@/stores'
import { useQuasar } from 'quasar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  currentBook: Book
}>()

const emit = defineEmits<{
  replaced: [book: Book]
}>()

const { t } = useI18n()
const $q = useQuasar()
const bookStore = useBookStore()
const userBookStore = useUserBookStore()

const showDialog = defineModel<boolean>({ default: false })

const searchQuery = ref('')
const searching = ref(false)
const searchResults = ref<Book[]>([])
const hasSearched = ref(false)
const showConfirmDialog = ref(false)
const selectedBook = ref<Book | null>(null)
const replacing = ref(false)

function searchBooks() {
  if (!searchQuery.value.trim()) return

  searching.value = true
  hasSearched.value = true

  bookStore
    .getBooks({ search: searchQuery.value })
    .then((response) => {
      searchResults.value = response.data
    })
    .catch((error) => {
      console.error(error)
      $q.notify({
        message: t('error-searching'),
        type: 'negative'
      })
    })
    .finally(() => {
      searching.value = false
    })
}

function selectBook(book: Book) {
  selectedBook.value = book
  showConfirmDialog.value = true
}

function confirmReplace() {
  if (!selectedBook.value) return

  replacing.value = true

  userBookStore
    .replaceUserBook(props.currentBook.id, selectedBook.value.id)
    .then((newBook) => {
      $q.notify({
        message: t('book-replaced-successfully'),
        type: 'positive'
      })
      emit('replaced', newBook)
      showDialog.value = false
      showConfirmDialog.value = false
    })
    .catch((error) => {
      console.error(error)
      // Error notification already handled in store
    })
    .finally(() => {
      replacing.value = false
    })
}
</script>
